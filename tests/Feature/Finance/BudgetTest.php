<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\ConsumptionEntry;
use App\Domain\Finance\DisbursementRecord;
use App\Enums\AuditAction;
use App\Enums\BudgetStatus;
use App\Enums\FeatureFlag;
use App\Enums\SystemRole;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\Barangay;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\BudgetVersion;
use App\Models\User;
use App\Services\Finance\FundConsumption;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesFinance;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use CreatesFinance, CreatesResidents, RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Barangay}
     */
    private function desk(): array
    {
        [$treasurer, $barangay] = $this->financeDesk();
        $captain = $this->barangayUser(SystemRole::BarangayAdmin, $barangay);
        $this->installChart($barangay);

        return [$treasurer, $captain, $barangay];
    }

    /**
     * @param  array<string, string>  $amounts
     * @return array{lines: list<array{account_id: int, amount: string}>}
     */
    private function lines(array $amounts): array
    {
        return ['lines' => array_values(array_map(
            fn (string $code, string $amount): array => ['account_id' => $this->account($code)->id, 'amount' => $amount],
            array_keys($amounts),
            $amounts,
        ))];
    }

    private function approvedBudget(User $treasurer, User $captain, int $year): Budget
    {
        $this->actingAs($treasurer)->post(route('finance.budgets.store'), ['fiscal_year' => $year])->assertSessionHasNoErrors();
        $budget = Budget::query()->where('fiscal_year', $year)->sole();
        $this->actingAs($treasurer)->put(route('finance.budgets.lines', $budget), $this->lines([
            '1-01' => '500000.00', '1-02' => '1000000.00', '2-01-01' => '600000.00', '2-02-03' => '150000.50', '2-03-01' => '300000.00',
        ]))->assertSessionHasNoErrors();
        $this->actingAs($captain)->post(route('finance.budgets.approve', $budget))->assertSessionHasNoErrors();

        return $budget->refresh();
    }

    public function test_a_budget_is_created_prepared_and_approved_by_different_roles(): void
    {
        [$treasurer, $captain] = $this->desk();

        $this->actingAs($treasurer)->post(route('finance.budgets.store'), ['fiscal_year' => 2031])->assertSessionHasNoErrors();
        $budget = Budget::query()->sole();
        $this->assertSame('Budget 2031', $budget->title);
        $this->actingAs($treasurer)->postJson(route('finance.budgets.store'), ['fiscal_year' => 2031])
            ->assertUnprocessable()->assertJsonPath('error.code', 'budget_year_taken');

        $this->actingAs($treasurer)->putJson(route('finance.budgets.lines', $budget), $this->lines(['2-02' => '10.00']))
            ->assertUnprocessable()->assertJsonPath('error.code', 'account_not_posting');
        $this->actingAs($treasurer)->put(route('finance.budgets.lines', $budget), $this->lines(['1-01' => '1000.00', '2-02-01' => '400.25', '2-02-02' => '0']))
            ->assertSessionHasNoErrors();
        $this->actingAs($treasurer)->post(route('finance.budgets.approve', $budget))->assertForbidden();
        $this->actingAs($captain)->post(route('finance.budgets.approve', $budget))->assertSessionHasNoErrors();

        $budget->refresh();
        $this->assertSame(BudgetStatus::Approved, $budget->status);
        $version = $budget->effectiveVersion();
        $this->assertNotNull($version);
        $this->assertSame(BudgetVersion::ORIGINAL, $version->number);
        $this->assertSame([$this->account('1-01')->id => 100_000, $this->account('2-02-01')->id => 40_025], $version->amounts());
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::BudgetApproved)->exists());

        $this->actingAs($treasurer)->putJson(route('finance.budgets.lines', $budget), $this->lines(['1-01' => '1.00']))
            ->assertUnprocessable()->assertJsonPath('error.code', 'budget_not_draft');
    }

    public function test_approved_versions_are_immutable_in_the_database(): void
    {
        [$treasurer, $captain] = $this->desk();
        $budget = $this->approvedBudget($treasurer, $captain, 2032);
        $version = $budget->effectiveVersion();
        $this->assertNotNull($version);

        $attempts = [
            fn () => DB::table('budget_lines')->where('budget_version_id', $version->id)->update(['amount' => '1.00']),
            fn () => DB::table('budget_lines')->where('budget_version_id', $version->id)->delete(),
            fn () => DB::table('budget_lines')->insert(['barangay_id' => $budget->barangay_id, 'budget_version_id' => $version->id, 'account_id' => $this->account('1-03')->id, 'amount' => '1.00']),
            fn () => DB::table('budget_versions')->where('id', $version->id)->update(['remarks' => 'changed']),
        ];

        foreach ($attempts as $attempt) {
            try {
                DB::transaction($attempt);
                $this->fail('An approved budget version was changed.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('approved', $exception->getMessage());
            }
        }

        $this->assertSame('500000.00', BudgetLine::query()->where('budget_version_id', $version->id)->where('account_id', $this->account('1-01')->id)->value('amount'));
    }

    public function test_revisions_are_zero_sum_need_remarks_and_keep_every_version(): void
    {
        [$treasurer, $captain] = $this->desk();
        $budget = $this->approvedBudget($treasurer, $captain, 2033);
        $travel = $this->account('2-02-01')->id;
        $supplies = $this->account('2-02-03')->id;

        $this->actingAs($treasurer)->postJson(route('finance.budgets.revise', $budget), ['lines' => [['account_id' => $supplies, 'amount' => '100000.50', 'remarks' => 'Move']]])
            ->assertUnprocessable()->assertJsonPath('error.code', 'budget_revision_unbalanced');
        $this->actingAs($treasurer)->postJson(route('finance.budgets.revise', $budget), ['lines' => [
            ['account_id' => $supplies, 'amount' => '100000.50', 'remarks' => 'Move'],
            ['account_id' => $travel, 'amount' => '50000.00'],
        ]])->assertUnprocessable()->assertJsonPath('error.code', 'budget_revision_needs_remarks');
        $this->actingAs($treasurer)->postJson(route('finance.budgets.revise', $budget), ['lines' => [['account_id' => $supplies, 'amount' => '150000.50']]])
            ->assertUnprocessable()->assertJsonPath('error.code', 'budget_revision_unchanged');

        $this->actingAs($treasurer)->post(route('finance.budgets.revise', $budget), ['remarks' => 'Resolution 7', 'lines' => [
            ['account_id' => $supplies, 'amount' => '100000.50', 'remarks' => 'Lower supply needs'],
            ['account_id' => $travel, 'amount' => '50000.00', 'remarks' => 'Seminar travel'],
        ]])->assertSessionHasNoErrors();

        $versions = $budget->versions()->orderBy('number')->get();
        $this->assertCount(2, $versions);
        $this->assertSame(15_000_050, $versions[0]->amounts()[$supplies]);
        $this->assertSame(10_000_050, $versions[1]->amounts()[$supplies]);
        $this->assertSame(5_000_000, $versions[1]->amounts()[$travel]);
        $this->assertTrue($versions[1]->isApproved());
        $this->assertSame(array_sum($versions[0]->amounts()), array_sum($versions[1]->amounts()));

        $this->actingAs($treasurer)->get(route('finance.budgets.show', $budget))->assertInertia(fn (Assert $page) => $page
            ->component('finance/budgets/Show')
            ->where('version', 2)
            ->has('history', 2)
            ->where('history.1.remarks', 'Resolution 7')
            ->has('history.1.changes', 2)
            ->where('history.1.changes.0.code', '2-02-01')
            ->where('history.1.changes.0.delta', '50000.00')
            ->where('history.1.changes.0.remarks', 'Seminar travel')
            ->where('history.1.changes.1.delta', '-50000.00'));
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::BudgetRevised)->count());
    }

    public function test_revisions_and_copies_follow_their_flags(): void
    {
        [$treasurer, $captain, $barangay] = $this->desk();
        $this->turnOff(FeatureFlag::BudgetRevision, $barangay);
        $this->turnOff(FeatureFlag::BudgetCopyPrevious, $barangay);
        $budget = $this->approvedBudget($treasurer, $captain, 2034);

        $this->actingAs($treasurer)->postJson(route('finance.budgets.revise', $budget), ['lines' => []])->assertNotFound();
        $this->actingAs($treasurer)->postJson(route('finance.budgets.copy', $budget), ['source_budget_id' => $budget->id])->assertNotFound();
    }

    public function test_copy_previous_fills_an_empty_draft_from_the_latest_approved_version(): void
    {
        [$treasurer, $captain] = $this->desk();
        $source = $this->approvedBudget($treasurer, $captain, 2035);
        $this->actingAs($treasurer)->post(route('finance.budgets.revise', $source), ['lines' => [
            ['account_id' => $this->account('2-02-03')->id, 'amount' => '100000.50', 'remarks' => 'Move'],
            ['account_id' => $this->account('2-02-01')->id, 'amount' => '50000.00', 'remarks' => 'Move'],
        ]])->assertSessionHasNoErrors();
        $this->account('2-03-01')->update(['is_active' => false]);

        $this->actingAs($treasurer)->post(route('finance.budgets.store'), ['fiscal_year' => 2036, 'title' => 'Annual budget'])->assertSessionHasNoErrors();
        $target = Budget::query()->where('fiscal_year', 2036)->sole();
        $this->actingAs($treasurer)->get(route('finance.budgets.show', $target))->assertInertia(fn (Assert $page) => $page->has('copySources', 1));

        $this->actingAs($treasurer)->post(route('finance.budgets.copy', $target), ['source_budget_id' => $source->id])->assertSessionHasNoErrors();
        $this->actingAs($treasurer)->postJson(route('finance.budgets.copy', $target), ['source_budget_id' => $source->id])
            ->assertUnprocessable()->assertJsonPath('error.code', 'budget_not_empty');

        $amounts = $target->latestVersion()->amounts();
        $this->assertSame(5_000_000, $amounts[$this->account('2-02-01')->id]);
        $this->assertArrayNotHasKey($this->account('2-03-01')->id, $amounts);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::BudgetCopied)->exists());
    }

    public function test_the_unappropriated_balance_is_computed_and_follows_its_flag(): void
    {
        [$treasurer, $captain, $barangay] = $this->desk();
        $budget = $this->approvedBudget($treasurer, $captain, 2037);

        $this->actingAs($treasurer)->get(route('finance.budgets.show', $budget))->assertInertia(fn (Assert $page) => $page
            ->where('totals.income', '1500000.00')
            ->where('totals.expense', '1050000.50')
            ->where('totals.unappropriated', '449999.50')
            ->where('rows.0.code', '1')
            ->where('rows.0.total', '1500000.00'));

        $this->assertFalse(DB::table('budget_lines')->where('amount', '449999.50')->exists());

        $this->turnOff(FeatureFlag::BudgetUnappropriatedRow, $barangay);
        $other = $this->barangayUser(SystemRole::Treasurer, $barangay);
        $this->actingAs($other)->get(route('finance.budgets.show', $budget))->assertInertia(fn (Assert $page) => $page->where('totals.unappropriated', null));
    }

    public function test_the_summary_subtracts_fund_consumption_and_rolls_up_the_tree(): void
    {
        [$treasurer, $captain] = $this->desk();
        $budget = $this->approvedBudget($treasurer, $captain, 2038);
        $supplies = $this->account('2-02-03');
        $this->app->instance(FundConsumption::class, new class($supplies->id) implements FundConsumption
        {
            public function __construct(private int $accountId) {}

            public function entriesByAccount(Budget $budget): array
            {
                return [$this->accountId => [new ConsumptionEntry(5_000_050), new ConsumptionEntry(99_999, cancelled: true)]];
            }

            public function disbursements(Budget $budget, Account $account): array
            {
                return [new DisbursementRecord('2038-01-001', '2038-01-15', 'Sample Supplies', 'Bond paper', 5_000_050)];
            }
        });

        $this->actingAs($treasurer)->get(route('finance.budgets.summary', $budget))->assertInertia(function (Assert $page) use ($supplies): void {
            $page->component('finance/budgets/Summary')
                ->where('totals.appropriation', '1050000.50')
                ->where('totals.consumed', '50000.50')
                ->where('totals.available', '1000000.00');
            $rows = collect($page->toArray()['props']['rows'])->keyBy('code');
            $this->assertSame('100000.00', $rows['2-02-03']['available']);
            $this->assertSame('100000.00', $rows['2-02']['available']);
            $this->assertSame('1000000.00', $rows['2']['available']);
            $this->assertSame($supplies->id, $rows['2-02-03']['id']);
        });

        $this->actingAs($treasurer)->get(route('finance.budgets.disbursements', [$budget, $supplies]))->assertInertia(fn (Assert $page) => $page
            ->component('finance/budgets/Disbursements')
            ->where('records.0.voucher_number', '2038-01-001')
            ->where('total', '50000.50'));
    }

    public function test_disbursement_history_is_empty_until_vouchers_exist(): void
    {
        [$treasurer, $captain] = $this->desk();
        $budget = $this->approvedBudget($treasurer, $captain, 2039);

        $this->actingAs($treasurer)->get(route('finance.budgets.disbursements', [$budget, $this->account('2-02-03')]))
            ->assertInertia(fn (Assert $page) => $page->has('records', 0)->where('total', '0.00'));
    }
}
