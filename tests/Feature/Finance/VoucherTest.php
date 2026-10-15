<?php

namespace Tests\Feature\Finance;

use App\Enums\AuditAction;
use App\Enums\CheckStatus;
use App\Enums\DocumentKind;
use App\Enums\FeatureFlag;
use App\Enums\SystemRole;
use App\Enums\VoucherStatus;
use App\Models\AuditEvent;
use App\Models\BankAccount;
use App\Models\Barangay;
use App\Models\Budget;
use App\Models\Check;
use App\Models\User;
use App\Models\Voucher;
use App\Queries\VoucherListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesFinance;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class VoucherTest extends TestCase
{
    use CreatesFinance, CreatesResidents, RefreshDatabase;

    private const int YEAR = 2031;

    /**
     * @return array{treasurer: User, bookkeeper: User, captain: User, barangay: Barangay}
     */
    private function desk(): array
    {
        [$treasurer, $barangay] = $this->financeDesk();
        $bookkeeper = $this->barangayUser(SystemRole::Bookkeeper, $barangay);
        $captain = $this->barangayUser(SystemRole::BarangayAdmin, $barangay);
        $this->installChart($barangay);

        return ['treasurer' => $treasurer, 'bookkeeper' => $bookkeeper, 'captain' => $captain, 'barangay' => $barangay];
    }

    /**
     * @param  array{treasurer: User, bookkeeper: User, captain: User, barangay: Barangay}  $desk
     * @param  array<string, string>  $items
     * @param  array<string, mixed>  $overrides
     */
    private function approvedVoucher(array $desk, Budget $budget, array $items, array $overrides = []): Voucher
    {
        $voucher = $this->verifiedVoucher($this->preparedVoucher($desk['treasurer'], $budget, $items, $overrides), $desk['bookkeeper']);
        $this->actingAs($desk['captain'])->post(route('finance.vouchers.approve', $voucher))->assertSessionHasNoErrors();
        $this->inBarangay($desk['barangay']);

        return $voucher->refresh();
    }

    /**
     * @return array<string, string>
     */
    private function available(User $viewer, Budget $budget, string $code): array
    {
        $row = [];
        $this->actingAs($viewer)->get(route('finance.budgets.summary', $budget))->assertInertia(function (Assert $page) use ($code, &$row) {
            $rows = $page->toArray()['props']['rows'];
            $row = collect($rows)->firstWhere('code', $code);
        });
        $this->inBarangay($budget->barangay()->firstOrFail());

        return ['consumed' => $row['consumed'], 'available' => $row['available']];
    }

    public function test_vouchers_are_numbered_at_save_and_total_their_items(): void
    {
        $desk = $this->desk();
        $budget = $this->approvedBudget($desk['captain'], self::YEAR, ['2-02-01' => '10000.00', '2-02-03' => '5000.00']);

        $this->actingAs($desk['treasurer'])->get(route('finance.vouchers.create'))->assertInertia(fn (Assert $page) => $page
            ->component('finance/vouchers/Form')
            ->where('budgetId', $budget->id)
            ->where('fundSources', fn ($sources) => collect($sources)->pluck('available')->all() === ['10000.00', '5000.00']));
        $this->assertSame(0, DB::table('document_sequences')->where('kind', DocumentKind::DisbursementVoucher->value)->count());

        $first = $this->preparedVoucher($desk['treasurer'], $budget, ['2-02-01' => '1500.25', '2-02-03' => '499.75']);
        $second = $this->preparedVoucher($desk['treasurer'], $budget, ['2-02-03' => '10.00']);

        $this->assertSame('DV-'.self::YEAR.'-000001', $first->voucher_number);
        $this->assertSame('DV-'.self::YEAR.'-000002', $second->voucher_number);
        $this->assertSame(VoucherStatus::Prepared, $first->status);
        $this->assertSame('Sample Payee', $first->payee_name);
        $this->assertSame('2000.00', app(VoucherListQuery::class)->find($first->id)->items_total);
        $this->assertSame([1, 2], $first->items()->pluck('position')->all());
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::VoucherPrepared)->where('subject_id', $first->id)->count());

        $this->actingAs($desk['treasurer'])->get(route('finance.vouchers.index'))->assertInertia(fn (Assert $page) => $page
            ->component('finance/vouchers/Index')
            ->where('vouchers.data.1.total', '2000.00'));
    }

    public function test_vouchers_reject_income_accounts_dates_outside_the_budget_year_and_empty_items(): void
    {
        $desk = $this->desk();
        $budget = $this->approvedBudget($desk['captain'], self::YEAR, ['1-01' => '9000.00', '2-02-01' => '10000.00']);

        $this->actingAs($desk['treasurer'])->postJson(route('finance.vouchers.store'), $this->voucherPayload($budget, ['1-01' => '10.00']))
            ->assertUnprocessable()->assertJsonPath('error.code', 'account_not_fund_source');
        $this->actingAs($desk['treasurer'])->postJson(route('finance.vouchers.store'), $this->voucherPayload($budget, ['2-02' => '10.00']))
            ->assertUnprocessable()->assertJsonPath('error.code', 'account_not_fund_source');
        $this->actingAs($desk['treasurer'])->post(route('finance.vouchers.store'), $this->voucherPayload($budget, ['2-02-01' => '10.00'], ['voucher_date' => (self::YEAR - 1).'-12-31']))
            ->assertSessionHasErrors('voucher_date');
        $this->actingAs($desk['treasurer'])->post(route('finance.vouchers.store'), $this->voucherPayload($budget, []))
            ->assertSessionHasErrors('items');
        $this->actingAs($desk['treasurer'])->post(route('finance.vouchers.store'), $this->voucherPayload($budget, ['2-02-01' => '0']))
            ->assertSessionHasErrors('items.0.amount');
        $this->actingAs($desk['treasurer'])->post(route('finance.vouchers.store'), $this->voucherPayload($budget, ['2-02-01' => '10.00'], ['payee_name' => null]))
            ->assertSessionHasErrors('payee_name');

        $this->inBarangay($desk['barangay']);
        $this->assertSame(0, Voucher::query()->count());
    }

    public function test_approval_refuses_amounts_beyond_the_available_balance(): void
    {
        $desk = $this->desk();
        $budget = $this->approvedBudget($desk['captain'], self::YEAR, ['2-02-01' => '1000.00']);

        $tooLarge = $this->verifiedVoucher($this->preparedVoucher($desk['treasurer'], $budget, ['2-02-01' => '1000.01']), $desk['bookkeeper']);
        $this->actingAs($desk['captain'])->postJson(route('finance.vouchers.approve', $tooLarge))
            ->assertUnprocessable()->assertJsonPath('error.code', 'insufficient_funds');

        $first = $this->verifiedVoucher($this->preparedVoucher($desk['treasurer'], $budget, ['2-02-01' => '600.00']), $desk['bookkeeper']);
        $second = $this->verifiedVoucher($this->preparedVoucher($desk['treasurer'], $budget, ['2-02-01' => '400.01']), $desk['bookkeeper']);

        $this->actingAs($desk['captain'])->post(route('finance.vouchers.approve', $first))->assertSessionHasNoErrors();
        $this->actingAs($desk['captain'])->postJson(route('finance.vouchers.approve', $second))
            ->assertUnprocessable()->assertJsonPath('error.code', 'insufficient_funds')
            ->assertJsonPath('error.message', 'Account 2-02-01 has 400.00 available, not enough for 400.01.');

        $this->inBarangay($desk['barangay']);
        $this->assertSame(VoucherStatus::Approved, $first->refresh()->status);
        $this->assertSame(VoucherStatus::Verified, $second->refresh()->status);
        $this->assertSame(['consumed' => '600.00', 'available' => '400.00'], $this->available($desk['treasurer'], $budget, '2-02-01'));
    }

    public function test_only_the_right_roles_move_a_voucher_through_its_steps(): void
    {
        $desk = $this->desk();
        $budget = $this->approvedBudget($desk['captain'], self::YEAR, ['2-02-01' => '1000.00']);
        $voucher = $this->preparedVoucher($desk['treasurer'], $budget, ['2-02-01' => '100.00']);

        $this->actingAs($desk['treasurer'])->post(route('finance.vouchers.verify', $voucher))->assertForbidden();
        $this->actingAs($desk['captain'])->postJson(route('finance.vouchers.approve', $voucher))
            ->assertUnprocessable()->assertJsonPath('error.code', 'voucher_wrong_status');
        $this->verifiedVoucher($voucher, $desk['bookkeeper']);
        $this->actingAs($desk['treasurer'])->post(route('finance.vouchers.approve', $voucher))->assertForbidden();
        $this->actingAs($desk['bookkeeper'])->post(route('finance.vouchers.approve', $voucher))->assertForbidden();
        $this->actingAs($desk['captain'])->post(route('finance.vouchers.approve', $voucher))->assertSessionHasNoErrors();

        $this->inBarangay($desk['barangay']);
        $voucher->refresh();
        $this->assertSame(VoucherStatus::Approved, $voucher->status);
        $this->assertSame($desk['bookkeeper']->id, $voucher->verified_by);
        $this->assertSame($desk['captain']->id, $voucher->approved_by);
    }

    public function test_edits_are_allowed_before_approval_only(): void
    {
        $desk = $this->desk();
        $budget = $this->approvedBudget($desk['captain'], self::YEAR, ['2-02-01' => '1000.00', '2-02-03' => '1000.00']);
        $voucher = $this->verifiedVoucher($this->preparedVoucher($desk['treasurer'], $budget, ['2-02-01' => '100.00']), $desk['bookkeeper']);

        $this->actingAs($desk['treasurer'])->put(route('finance.vouchers.update', $voucher), $this->voucherPayload($budget, ['2-02-03' => '250.00'], ['particulars' => 'Changed']))
            ->assertSessionHasNoErrors();
        $this->inBarangay($desk['barangay']);
        $voucher->refresh();
        $this->assertSame(VoucherStatus::Prepared, $voucher->status);
        $this->assertNull($voucher->verified_by);
        $this->assertSame('250.00', app(VoucherListQuery::class)->find($voucher->id)->items_total);
        $updated = AuditEvent::query()->where('action', AuditAction::VoucherUpdated)->sole();
        $this->assertSame('Changed', $updated->changes['after']['particulars']);

        $this->verifiedVoucher($voucher, $desk['bookkeeper']);
        $this->actingAs($desk['captain'])->post(route('finance.vouchers.approve', $voucher))->assertSessionHasNoErrors();
        $this->actingAs($desk['treasurer'])->putJson(route('finance.vouchers.update', $voucher), $this->voucherPayload($budget, ['2-02-03' => '1.00']))
            ->assertUnprocessable()->assertJsonPath('error.code', 'voucher_not_editable');
    }

    public function test_cancel_voids_an_unreleased_check_and_returns_the_funds(): void
    {
        $desk = $this->desk();
        $budget = $this->approvedBudget($desk['captain'], self::YEAR, ['2-02-01' => '1000.00']);
        $bank = BankAccount::factory()->create();
        $voucher = $this->approvedVoucher($desk, $budget, ['2-02-01' => '600.00']);
        $check = $this->issueCheck($desk['treasurer'], $voucher, $bank->id, '5001');
        $this->assertSame(['consumed' => '600.00', 'available' => '400.00'], $this->available($desk['treasurer'], $budget, '2-02-01'));

        $this->actingAs($desk['treasurer'])->post(route('finance.vouchers.cancel', $voucher), ['reason' => 'Duplicate request'])->assertSessionHasNoErrors();

        $this->inBarangay($desk['barangay']);
        $this->assertSame(VoucherStatus::Cancelled, $voucher->refresh()->status);
        $check->refresh();
        $this->assertSame(CheckStatus::Cancelled, $check->status);
        $this->assertSame('Duplicate request', $check->cancel_reason);
        $this->assertSame(['consumed' => '0.00', 'available' => '1000.00'], $this->available($desk['treasurer'], $budget, '2-02-01'));
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::CheckCancelled)->count());
        $this->actingAs($desk['treasurer'])->postJson(route('finance.vouchers.cancel', $voucher), ['reason' => 'Again'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'voucher_wrong_status');
    }

    public function test_a_released_check_blocks_the_cancel(): void
    {
        $desk = $this->desk();
        $budget = $this->approvedBudget($desk['captain'], self::YEAR, ['2-02-01' => '1000.00']);
        $bank = BankAccount::factory()->create();
        $voucher = $this->approvedVoucher($desk, $budget, ['2-02-01' => '600.00']);
        $check = $this->issueCheck($desk['treasurer'], $voucher, $bank->id, '5002');
        $this->actingAs($desk['treasurer'])->post(route('finance.checks.release', $check), ['received_by' => 'Sample Receiver', 'confirmed' => true])->assertSessionHasNoErrors();

        $this->actingAs($desk['treasurer'])->postJson(route('finance.vouchers.cancel', $voucher), ['reason' => 'Too late'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'voucher_has_released_check');

        $this->inBarangay($desk['barangay']);
        $this->assertSame(VoucherStatus::Closed, $voucher->refresh()->status);
        $this->assertSame(CheckStatus::Released, $check->refresh()->status);
    }

    public function test_others_vouchers_are_released_at_approval_without_a_check(): void
    {
        $desk = $this->desk();
        $budget = $this->approvedBudget($desk['captain'], self::YEAR, ['2-02-01' => '1000.00']);

        $voucher = $this->approvedVoucher($desk, $budget, ['2-02-01' => '250.00'], ['payment_type' => 'others']);

        $this->assertSame(VoucherStatus::Closed, $voucher->status);
        $this->assertNotNull($voucher->released_at);
        $this->assertSame(0, Check::query()->count());
        $this->assertSame(['consumed' => '250.00', 'available' => '750.00'], $this->available($desk['treasurer'], $budget, '2-02-01'));
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::VoucherReleased)->count());
        $this->actingAs($desk['treasurer'])->get(route('finance.vouchers.print', $voucher))->assertOk();
        $this->actingAs($desk['treasurer'])->postJson(route('finance.vouchers.cancel', $voucher), ['reason' => 'Paid already'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'voucher_paid_without_check');
    }

    public function test_others_vouchers_need_a_check_when_auto_release_is_off(): void
    {
        $desk = $this->desk();
        $this->turnOff(FeatureFlag::VoucherOthersAutoRelease, $desk['barangay']);
        $budget = $this->approvedBudget($desk['captain'], self::YEAR, ['2-02-01' => '1000.00']);

        $voucher = $this->approvedVoucher($desk, $budget, ['2-02-01' => '250.00'], ['payment_type' => 'others']);

        $this->assertSame(VoucherStatus::Approved, $voucher->status);
        $this->assertNull($voucher->released_at);
    }

    public function test_printing_a_check_voucher_waits_for_its_check(): void
    {
        $desk = $this->desk();
        $budget = $this->approvedBudget($desk['captain'], self::YEAR, ['2-02-01' => '1000.00']);
        $bank = BankAccount::factory()->create();
        $voucher = $this->approvedVoucher($desk, $budget, ['2-02-01' => '1234.56'], ['items' => [
            ['account_id' => $this->account('2-02-01')->id, 'description' => 'Fuel', 'amount' => '234.56'],
            ['account_id' => $this->account('2-02-01')->id, 'description' => 'Meals', 'amount' => '765.44'],
        ]]);

        $this->actingAs($desk['treasurer'])->getJson(route('finance.vouchers.print', $voucher))
            ->assertUnprocessable()->assertJsonPath('error.code', 'voucher_needs_check');

        $this->issueCheck($desk['treasurer'], $voucher, $bank->id, '7001');
        $this->actingAs($desk['treasurer'])->get(route('finance.vouchers.print', $voucher))->assertInertia(fn (Assert $page) => $page
            ->component('finance/vouchers/Print')
            ->where('voucher.total', '1000.00')
            ->where('check.check_number', '7001')
            ->where('amountInWords', 'One Thousand Pesos Only')
            ->where('minRows', 8));

        $this->inBarangay($desk['barangay']);
        $this->assertSame(1, $voucher->refresh()->print_count);
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::VoucherPrinted)->count());
    }

    public function test_voucher_printing_without_a_check_is_allowed_when_the_flag_is_off(): void
    {
        $desk = $this->desk();
        $this->turnOff(FeatureFlag::DvPrintRequiresCheck, $desk['barangay']);
        $budget = $this->approvedBudget($desk['captain'], self::YEAR, ['2-02-01' => '1000.00']);
        $voucher = $this->preparedVoucher($desk['treasurer'], $budget, ['2-02-01' => '10.00']);

        $this->actingAs($desk['treasurer'])->get(route('finance.vouchers.print', $voucher))->assertOk();
    }

    public function test_budget_summary_and_disbursement_history_show_real_disbursements(): void
    {
        $desk = $this->desk();
        $budget = $this->approvedBudget($desk['captain'], self::YEAR, ['2-02-01' => '1000.00', '2-02-03' => '500.00']);
        $bank = BankAccount::factory()->create();
        $released = $this->approvedVoucher($desk, $budget, ['2-02-01' => '300.00', '2-02-03' => '125.50'], ['payee_name' => 'Hardware Sample']);
        $check = $this->issueCheck($desk['treasurer'], $released, $bank->id, '8001');
        $this->actingAs($desk['treasurer'])->post(route('finance.checks.release', $check), ['received_by' => 'Sample Receiver', 'confirmed' => true])->assertSessionHasNoErrors();
        $this->approvedVoucher($desk, $budget, ['2-02-01' => '200.00']);
        $this->preparedVoucher($desk['treasurer'], $budget, ['2-02-01' => '50.00']);

        $this->assertSame(['consumed' => '500.00', 'available' => '500.00'], $this->available($desk['treasurer'], $budget, '2-02-01'));
        $this->assertSame(['consumed' => '125.50', 'available' => '374.50'], $this->available($desk['treasurer'], $budget, '2-02-03'));
        $this->assertSame(['consumed' => '625.50', 'available' => '874.50'], $this->available($desk['treasurer'], $budget, '2-02'));

        $this->actingAs($desk['treasurer'])->get(route('finance.budgets.disbursements', [$budget, $this->account('2-02-01')]))->assertInertia(fn (Assert $page) => $page
            ->component('finance/budgets/Disbursements')
            ->has('records', 1)
            ->where('records.0.voucher_number', $released->voucher_number)
            ->where('records.0.payee', 'Hardware Sample')
            ->where('records.0.amount', '300.00')
            ->where('records.0.check_number', '8001')
            ->where('total', '300.00'));
    }

    public function test_a_revision_cannot_cut_an_account_below_what_is_approved_for_disbursement(): void
    {
        $desk = $this->desk();
        $budget = $this->approvedBudget($desk['captain'], self::YEAR, ['2-02-01' => '1000.00', '2-02-03' => '1000.00']);
        $this->approvedVoucher($desk, $budget, ['2-02-01' => '800.00']);

        $this->actingAs($desk['treasurer'])->postJson(route('finance.budgets.revise', $budget), ['lines' => [
            ['account_id' => $this->account('2-02-01')->id, 'amount' => '700.00', 'remarks' => 'Move'],
            ['account_id' => $this->account('2-02-03')->id, 'amount' => '1300.00', 'remarks' => 'Move'],
        ]])->assertUnprocessable()->assertJsonPath('error.code', 'budget_revision_below_committed');

        $this->actingAs($desk['treasurer'])->post(route('finance.budgets.revise', $budget), ['lines' => [
            ['account_id' => $this->account('2-02-01')->id, 'amount' => '800.00', 'remarks' => 'Move'],
            ['account_id' => $this->account('2-02-03')->id, 'amount' => '1200.00', 'remarks' => 'Move'],
        ]])->assertSessionHasNoErrors();
    }
}
