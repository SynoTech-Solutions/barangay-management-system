<?php

namespace Tests\Feature\Finance;

use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Enums\LiquidationStatus;
use App\Enums\SystemRole;
use App\Enums\VoucherStatus;
use App\Models\AuditEvent;
use App\Models\BankAccount;
use App\Models\Barangay;
use App\Models\Budget;
use App\Models\CollectionType;
use App\Models\Liquidation;
use App\Models\OfficialReceipt;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesFinance;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class LiquidationTest extends TestCase
{
    use CreatesFinance, CreatesResidents, RefreshDatabase;

    private const int YEAR = 2033;

    /**
     * @return array{treasurer: User, bookkeeper: User, captain: User, barangay: Barangay, budget: Budget}
     */
    private function desk(string $appropriation = '10000.00'): array
    {
        [$treasurer, $barangay] = $this->financeDesk();
        $bookkeeper = $this->barangayUser(SystemRole::Bookkeeper, $barangay);
        $captain = $this->barangayUser(SystemRole::BarangayAdmin, $barangay);
        $this->installChart($barangay);
        $budget = $this->approvedBudget($captain, self::YEAR, ['2-02-01' => $appropriation]);

        return ['treasurer' => $treasurer, 'bookkeeper' => $bookkeeper, 'captain' => $captain, 'barangay' => $barangay, 'budget' => $budget];
    }

    /**
     * @param  array{treasurer: User, bookkeeper: User, captain: User, barangay: Barangay, budget: Budget}  $desk
     */
    private function releasedCashAdvance(array $desk, string $number): Voucher
    {
        $item = $this->account('2-02-01')->id;
        $voucher = $this->verifiedVoucher($this->preparedVoucher($desk['treasurer'], $desk['budget'], [], [
            'payment_type' => 'cash-advance',
            'items' => [
                ['account_id' => $item, 'description' => 'Seminar fees', 'amount' => '3000.00'],
                ['account_id' => $item, 'description' => 'Meals', 'amount' => '2000.00'],
            ],
        ]), $desk['bookkeeper']);
        $this->actingAs($desk['captain'])->post(route('finance.vouchers.approve', $voucher))->assertSessionHasNoErrors();
        $this->inBarangay($desk['barangay']);
        $check = $this->issueCheck($desk['treasurer'], $voucher, BankAccount::factory()->create()->id, $number);
        $this->actingAs($desk['treasurer'])->post(route('finance.checks.release', $check), ['received_by' => 'Sample Officer', 'confirmed' => true])->assertSessionHasNoErrors();
        $this->inBarangay($desk['barangay']);

        return $voucher->refresh();
    }

    /**
     * @param  list<string>  $actuals
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Voucher $voucher, array $actuals, array $overrides = []): array
    {
        $items = $voucher->items()->orderBy('position')->pluck('id')->all();

        return [
            'liquidated_on' => now()->toDateString(),
            'items' => array_map(fn (int $id, string $amount): array => ['voucher_item_id' => $id, 'actual_amount' => $amount], array_slice($items, 0, count($actuals)), $actuals),
            ...$overrides,
        ];
    }

    private function consumed(User $viewer, Budget $budget): string
    {
        $consumed = '';
        $this->actingAs($viewer)->get(route('finance.budgets.summary', $budget))->assertInertia(function (Assert $page) use (&$consumed) {
            $consumed = collect($page->toArray()['props']['rows'])->firstWhere('code', '2-02-01')['consumed'];
        });
        $this->inBarangay($budget->barangay()->firstOrFail());

        return $consumed;
    }

    public function test_a_refund_needs_a_receipt_and_clearing_returns_the_unspent_money(): void
    {
        $desk = $this->desk();
        $voucher = $this->releasedCashAdvance($desk, 'CA-1');
        $this->assertSame(VoucherStatus::Released, $voucher->status);

        $this->actingAs($desk['treasurer'])->get(route('finance.liquidations.index'))->assertInertia(fn (Assert $page) => $page
            ->component('finance/liquidations/Index')
            ->where('unliquidated.data.0.voucher_number', $voucher->voucher_number)
            ->where('unliquidated.data.0.total', '5000.00'));

        $this->actingAs($desk['treasurer'])->postJson(route('finance.liquidations.store', $voucher), $this->payload($voucher, ['2500.00', '1800.00']))
            ->assertUnprocessable()->assertJsonPath('error.code', 'liquidation_needs_receipt')
            ->assertJsonPath('error.message', 'A refund of 700.00 needs an OR number and OR date.');
        $this->actingAs($desk['treasurer'])->postJson(route('finance.liquidations.store', $voucher), $this->payload($voucher, ['2500.00']))
            ->assertUnprocessable()->assertJsonPath('error.code', 'liquidation_items_mismatch');

        $this->actingAs($desk['treasurer'])->post(route('finance.liquidations.store', $voucher), $this->payload($voucher, ['2500.00', '1800.00'], ['or_number' => 'OR-700', 'or_date' => now()->toDateString()]))
            ->assertSessionHasNoErrors()->assertRedirect(route('finance.liquidations.index'));

        $this->inBarangay($desk['barangay']);
        $liquidation = Liquidation::query()->sole();
        $this->assertSame('LR-'.self::YEAR.'-000001', $liquidation->liquidation_number);
        $this->assertSame(LiquidationStatus::ForClearing, $liquidation->status);
        $this->assertSame(VoucherStatus::Closed, $voucher->refresh()->status);
        $this->assertSame('OR-700', OfficialReceipt::query()->sole()->or_number);
        $this->assertSame('5000.00', $this->consumed($desk['treasurer'], $desk['budget']));

        $this->actingAs($desk['treasurer'])->post(route('finance.liquidations.clear', $liquidation))->assertForbidden();
        $this->actingAs($desk['bookkeeper'])->post(route('finance.liquidations.clear', $liquidation))->assertSessionHasNoErrors();

        $this->inBarangay($desk['barangay']);
        $this->assertSame(LiquidationStatus::Cleared, $liquidation->refresh()->status);
        $this->assertSame($desk['bookkeeper']->id, $liquidation->cleared_by);
        $this->assertSame('4300.00', $this->consumed($desk['treasurer'], $desk['budget']));
        $cleared = AuditEvent::query()->where('action', AuditAction::LiquidationCleared)->sole();
        $this->assertSame(['700.00', '0.00'], [$cleared->changes['after']['refund'], $cleared->changes['after']['reimbursement']]);
        $this->actingAs($desk['treasurer'])->putJson(route('finance.liquidations.update', $liquidation), $this->payload($voucher, ['3000.00', '2000.00']))
            ->assertUnprocessable()->assertJsonPath('error.code', 'liquidation_cleared');
    }

    public function test_one_liquidation_per_voucher(): void
    {
        $desk = $this->desk();
        $voucher = $this->releasedCashAdvance($desk, 'CA-2');
        $this->actingAs($desk['treasurer'])->post(route('finance.liquidations.store', $voucher), $this->payload($voucher, ['3000.00', '2000.00']))->assertSessionHasNoErrors();

        $this->actingAs($desk['treasurer'])->postJson(route('finance.liquidations.store', $voucher), $this->payload($voucher, ['3000.00', '2000.00']))
            ->assertUnprocessable()->assertJsonPath('error.code', 'voucher_not_unliquidated');

        $this->inBarangay($desk['barangay']);
        $this->expectException(QueryException::class);
        Liquidation::factory()->create(['voucher_id' => $voucher->id]);
    }

    public function test_a_reimbursement_consumes_more_only_when_the_fund_covers_it(): void
    {
        $desk = $this->desk('5300.00');
        $voucher = $this->releasedCashAdvance($desk, 'CA-3');
        $this->actingAs($desk['treasurer'])->post(route('finance.liquidations.store', $voucher), $this->payload($voucher, ['3500.00', '2000.00']))->assertSessionHasNoErrors();
        $this->inBarangay($desk['barangay']);
        $liquidation = Liquidation::query()->sole();

        $this->actingAs($desk['bookkeeper'])->postJson(route('finance.liquidations.clear', $liquidation))
            ->assertUnprocessable()->assertJsonPath('error.code', 'insufficient_funds');

        $this->actingAs($desk['treasurer'])->put(route('finance.liquidations.update', $liquidation), $this->payload($voucher, ['3300.00', '2000.00']))->assertSessionHasNoErrors();
        $this->actingAs($desk['bookkeeper'])->post(route('finance.liquidations.clear', $liquidation))->assertSessionHasNoErrors();

        $this->inBarangay($desk['barangay']);
        $this->assertSame('5300.00', $this->consumed($desk['treasurer'], $desk['budget']));
        $this->actingAs($desk['treasurer'])->get(route('finance.liquidations.index', ['status' => 'cleared']))->assertInertia(fn (Assert $page) => $page
            ->where('liquidations.data.0.reimbursement', '300.00')
            ->where('liquidations.data.0.refund', '0.00'));
    }

    public function test_the_refund_receipt_number_is_unique_across_documents(): void
    {
        $desk = $this->desk();
        $voucher = $this->releasedCashAdvance($desk, 'CA-4');
        $this->actingAs($desk['treasurer'])->post(route('collections.store'), [
            'collection_type_id' => CollectionType::factory()->create()->id, 'payer_name' => 'Sample Payer', 'or_number' => 'OR-55', 'amount' => '10.00', 'paid_on' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->actingAs($desk['treasurer'])->post(route('finance.liquidations.store', $voucher), $this->payload($voucher, ['2000.00', '2000.00'], ['or_number' => 'or-55', 'or_date' => now()->toDateString()]))
            ->assertSessionHasErrors('or_number');
    }

    public function test_without_the_clearing_step_a_liquidation_clears_at_once(): void
    {
        $desk = $this->desk();
        $this->turnOff(FeatureFlag::LiquidationClearing, $desk['barangay']);
        $voucher = $this->releasedCashAdvance($desk, 'CA-5');

        $this->actingAs($desk['treasurer'])->post(route('finance.liquidations.store', $voucher), $this->payload($voucher, ['3000.00', '1000.00'], ['or_number' => 'OR-1000', 'or_date' => now()->toDateString()]))
            ->assertSessionHasNoErrors();

        $this->inBarangay($desk['barangay']);
        $liquidation = Liquidation::query()->sole();
        $this->assertSame(LiquidationStatus::Cleared, $liquidation->status);
        $this->assertSame('4000.00', $this->consumed($desk['treasurer'], $desk['budget']));
        $this->actingAs($desk['bookkeeper'])->postJson(route('finance.liquidations.clear', $liquidation))->assertNotFound();
    }

    public function test_the_liquidation_report_prints_each_item(): void
    {
        $desk = $this->desk();
        $voucher = $this->releasedCashAdvance($desk, 'CA-6');
        $this->actingAs($desk['treasurer'])->post(route('finance.liquidations.store', $voucher), $this->payload($voucher, ['3000.00', '2100.00']))->assertSessionHasNoErrors();
        $this->inBarangay($desk['barangay']);
        $liquidation = Liquidation::query()->sole();
        $first = $voucher->items()->orderBy('position')->value('id');

        $this->actingAs($desk['treasurer'])->get(route('finance.liquidations.print', $liquidation))->assertInertia(fn (Assert $page) => $page
            ->component('finance/liquidations/Print')
            ->where('liquidation.advanced', '5000.00')
            ->where('liquidation.actual', '5100.00')
            ->where('liquidation.reimbursement', '100.00')
            ->where("descriptions.{$first}.original_in_words", 'Three Thousand Pesos Only')
            ->where('voucher.voucher_number', $voucher->voucher_number));
    }

    public function test_turning_liquidation_off_closes_cash_advances_on_release_and_hides_the_screens(): void
    {
        $desk = $this->desk();
        $this->turnOff(FeatureFlag::CashAdvanceLiquidation, $desk['barangay']);

        $voucher = $this->releasedCashAdvance($desk, 'CA-7');

        $this->assertSame(VoucherStatus::Closed, $voucher->status);
        $this->actingAs($desk['treasurer'])->get(route('finance.liquidations.index'))->assertNotFound();
        $this->actingAs($desk['treasurer'])->get(route('finance.liquidations.create', $voucher))->assertNotFound();
    }
}
