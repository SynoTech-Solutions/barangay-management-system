<?php

namespace Tests\Feature\Finance;

use App\Enums\AuditAction;
use App\Enums\CheckStatus;
use App\Enums\ErrorCode;
use App\Enums\FeatureFlag;
use App\Enums\SystemRole;
use App\Enums\VoucherStatus;
use App\Exceptions\BmsException;
use App\Models\AuditEvent;
use App\Models\BankAccount;
use App\Models\Barangay;
use App\Models\Budget;
use App\Models\Check;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Voucher;
use App\Services\Finance\CheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesFinance;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class CheckTest extends TestCase
{
    use CreatesFinance, CreatesResidents, RefreshDatabase;

    private const int YEAR = 2032;

    /**
     * @return array{treasurer: User, bookkeeper: User, captain: User, barangay: Barangay, budget: Budget}
     */
    private function desk(): array
    {
        [$treasurer, $barangay] = $this->financeDesk();
        $bookkeeper = $this->barangayUser(SystemRole::Bookkeeper, $barangay);
        $captain = $this->barangayUser(SystemRole::BarangayAdmin, $barangay);
        $this->installChart($barangay);
        $budget = $this->approvedBudget($captain, self::YEAR, ['2-02-01' => '50000.00']);

        return ['treasurer' => $treasurer, 'bookkeeper' => $bookkeeper, 'captain' => $captain, 'barangay' => $barangay, 'budget' => $budget];
    }

    /**
     * @param  array{treasurer: User, bookkeeper: User, captain: User, barangay: Barangay, budget: Budget}  $desk
     * @param  array<string, mixed>  $overrides
     */
    private function approvedVoucher(array $desk, string $amount, array $overrides = []): Voucher
    {
        $voucher = $this->verifiedVoucher($this->preparedVoucher($desk['treasurer'], $desk['budget'], ['2-02-01' => $amount], $overrides), $desk['bookkeeper']);
        $this->actingAs($desk['captain'])->post(route('finance.vouchers.approve', $voucher))->assertSessionHasNoErrors();
        $this->inBarangay($desk['barangay']);

        return $voucher->refresh();
    }

    public function test_a_voucher_check_carries_the_voucher_total_and_only_one_is_active(): void
    {
        $desk = $this->desk();
        $bank = BankAccount::factory()->create();
        $voucher = $this->approvedVoucher($desk, '1500.75');

        $check = $this->issueCheck($desk['treasurer'], $voucher, $bank->id, '000101');

        $this->assertSame('1500.75', $check->amount);
        $this->assertSame('Sample Payee', $check->payee_name);
        $this->assertSame(CheckStatus::ForRelease, $check->status);
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::CheckIssued)->count());
        $this->actingAs($desk['treasurer'])->postJson(route('finance.vouchers.checks.store', $voucher), ['bank_account_id' => $bank->id, 'check_number' => '000102', 'check_date' => now()->toDateString()])
            ->assertUnprocessable()->assertJsonPath('error.code', 'voucher_has_check');

        $prepared = $this->preparedVoucher($desk['treasurer'], $desk['budget'], ['2-02-01' => '10.00']);
        $this->actingAs($desk['treasurer'])->postJson(route('finance.vouchers.checks.store', $prepared), ['bank_account_id' => $bank->id, 'check_number' => '000103', 'check_date' => now()->toDateString()])
            ->assertUnprocessable()->assertJsonPath('error.code', 'voucher_wrong_status');
    }

    public function test_check_numbers_are_unique_per_bank_account_among_active_checks(): void
    {
        $desk = $this->desk();
        $first = BankAccount::factory()->create();
        $second = BankAccount::factory()->create();
        $check = $this->issueCheck($desk['treasurer'], $this->approvedVoucher($desk, '100.00'), $first->id, '1001');
        $voucher = $this->approvedVoucher($desk, '200.00');

        $this->actingAs($desk['treasurer'])->post(route('finance.vouchers.checks.store', $voucher), ['bank_account_id' => $first->id, 'check_number' => '1001', 'check_date' => now()->toDateString()])
            ->assertSessionHasErrors('check_number');

        $this->inBarangay($desk['barangay']);
        try {
            app(CheckService::class)->issueForVoucher($voucher, ['bank_account_id' => $first->id, 'check_number' => '1001', 'check_date' => now()->toDateString(), 'remarks' => null], $desk['treasurer']);
            $this->fail('A duplicate check number was saved.');
        } catch (BmsException $exception) {
            $this->assertSame(ErrorCode::CheckNumberTaken, $exception->errorCode);
        }

        $this->issueCheck($desk['treasurer'], $voucher, $second->id, '1001');
        $this->actingAs($desk['treasurer'])->post(route('finance.checks.cancel', $check), ['reason' => 'Spoiled'])->assertSessionHasNoErrors();
        $third = $this->approvedVoucher($desk, '300.00');
        $this->issueCheck($desk['treasurer'], $third, $first->id, '1001');

        $this->assertSame(3, Check::query()->where('check_number', '1001')->count());
    }

    public function test_release_needs_a_receiver_and_a_confirmation(): void
    {
        $desk = $this->desk();
        $bank = BankAccount::factory()->create();
        $voucher = $this->approvedVoucher($desk, '100.00');
        $check = $this->issueCheck($desk['treasurer'], $voucher, $bank->id, '2001');

        $this->actingAs($desk['treasurer'])->post(route('finance.checks.release', $check), ['received_by' => 'Sample Receiver'])
            ->assertSessionHasErrors('confirmed');
        $this->actingAs($desk['treasurer'])->post(route('finance.checks.release', $check), ['confirmed' => true])
            ->assertSessionHasErrors('received_by');
        $this->actingAs($desk['bookkeeper'])->post(route('finance.checks.release', $check), ['received_by' => 'Sample Receiver', 'confirmed' => true])
            ->assertForbidden();
        $this->inBarangay($desk['barangay']);
        $this->assertSame(CheckStatus::ForRelease, $check->refresh()->status);

        $this->actingAs($desk['treasurer'])->post(route('finance.checks.release', $check), ['received_by' => 'Sample  Receiver', 'confirmed' => true])
            ->assertSessionHasNoErrors();

        $this->inBarangay($desk['barangay']);
        $check->refresh();
        $this->assertSame(CheckStatus::Released, $check->status);
        $this->assertSame('Sample Receiver', $check->received_by_name);
        $this->assertSame($desk['treasurer']->id, $check->released_by);
        $this->assertSame(VoucherStatus::Closed, $voucher->refresh()->status);
        $this->actingAs($desk['treasurer'])->postJson(route('finance.checks.release', $check), ['received_by' => 'Again', 'confirmed' => true])
            ->assertUnprocessable()->assertJsonPath('error.code', 'check_not_for_release');
        $this->actingAs($desk['treasurer'])->postJson(route('finance.checks.cancel', $check), ['reason' => 'Lost'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'check_not_for_release');
        $this->actingAs($desk['treasurer'])->putJson(route('finance.checks.update', $check), ['bank_account_id' => $bank->id, 'check_number' => '2002', 'check_date' => now()->toDateString()])
            ->assertUnprocessable()->assertJsonPath('error.code', 'check_not_for_release');
    }

    public function test_a_cash_advance_stays_unliquidated_after_release(): void
    {
        $desk = $this->desk();
        $bank = BankAccount::factory()->create();
        $voucher = $this->approvedVoucher($desk, '100.00', ['payment_type' => 'cash-advance']);
        $check = $this->issueCheck($desk['treasurer'], $voucher, $bank->id, '3001');

        $this->actingAs($desk['treasurer'])->post(route('finance.checks.release', $check), ['received_by' => 'Sample Receiver', 'confirmed' => true])->assertSessionHasNoErrors();

        $this->inBarangay($desk['barangay']);
        $voucher->refresh();
        $this->assertSame(VoucherStatus::Released, $voucher->status);
        $this->assertNull($voucher->closed_at);
    }

    public function test_editing_a_voucher_check_keeps_its_amount(): void
    {
        $desk = $this->desk();
        $bank = BankAccount::factory()->create();
        $other = BankAccount::factory()->create();
        $check = $this->issueCheck($desk['treasurer'], $this->approvedVoucher($desk, '100.00'), $bank->id, '4001');

        $this->actingAs($desk['treasurer'])->put(route('finance.checks.update', $check), [
            'bank_account_id' => $other->id, 'check_number' => '4002', 'check_date' => now()->toDateString(), 'amount' => '999.00', 'remarks' => 'Reissued on new stock',
        ])->assertSessionHasNoErrors();

        $this->inBarangay($desk['barangay']);
        $check->refresh();
        $this->assertSame([$other->id, '4002', '100.00', 'Reissued on new stock'], [$check->bank_account_id, $check->check_number, $check->amount, $check->remarks]);
        $this->assertSame($desk['treasurer']->id, $check->issued_by);
        $this->assertSame($desk['treasurer']->id, $check->updated_by);
    }

    public function test_direct_checks_need_the_permission(): void
    {
        $desk = $this->desk();
        $bank = BankAccount::factory()->create();
        $vendor = Vendor::factory()->create();
        $payload = ['vendor_id' => $vendor->id, 'amount' => '2500.00', 'bank_account_id' => $bank->id, 'check_number' => '9001', 'check_date' => now()->toDateString()];

        $this->actingAs($desk['treasurer'])->get(route('finance.checks.direct.create'))->assertForbidden();
        $this->actingAs($desk['treasurer'])->post(route('finance.checks.direct.store'), $payload)->assertForbidden();
        $this->actingAs($desk['captain'])->post(route('finance.checks.direct.store'), [...$payload, 'amount' => '0.99'])->assertSessionHasErrors('amount');
        $this->actingAs($desk['captain'])->post(route('finance.checks.direct.store'), $payload)->assertSessionHasNoErrors()->assertRedirect(route('finance.checks.index'));

        $this->inBarangay($desk['barangay']);
        $check = Check::query()->sole();
        $this->assertNull($check->voucher_id);
        $this->assertSame([$vendor->name, '2500.00'], [$check->payee_name, $check->amount]);
        $this->actingAs($desk['treasurer'])->put(route('finance.checks.update', $check), [...$payload, 'amount' => '10.00'])->assertForbidden();
        $this->actingAs($desk['treasurer'])->post(route('finance.checks.release', $check), ['received_by' => 'Supplier Agent', 'confirmed' => true])->assertSessionHasNoErrors();
    }

    public function test_direct_checks_need_the_flag(): void
    {
        $desk = $this->desk();
        $this->turnOff(FeatureFlag::DirectCheckIssuance, $desk['barangay']);

        $this->actingAs($desk['captain'])->get(route('finance.checks.direct.create'))->assertNotFound();
        $this->actingAs($desk['captain'])->postJson(route('finance.checks.direct.store'), [])->assertNotFound();
    }

    public function test_check_print_writes_the_amount_in_words(): void
    {
        $desk = $this->desk();
        $bank = BankAccount::factory()->create();
        $check = $this->issueCheck($desk['treasurer'], $this->approvedVoucher($desk, '1234.56'), $bank->id, '6001');

        $this->actingAs($desk['treasurer'])->get(route('finance.checks.print', $check))->assertInertia(fn (Assert $page) => $page
            ->component('finance/checks/Print')
            ->where('check.amount', '1234.56')
            ->where('check.amount_in_words', 'One Thousand Two Hundred Thirty Four Pesos And Fifty Six Cents Only')
            ->where('check.print_count', 1));
        $this->actingAs($desk['bookkeeper'])->get(route('finance.checks.print', $check))->assertForbidden();

        $this->inBarangay($desk['barangay']);
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::CheckPrinted)->count());
    }

    public function test_check_print_follows_the_uppercase_flag(): void
    {
        $desk = $this->desk();
        $this->turnOn(FeatureFlag::AmountInWordsUppercase, $desk['barangay']);
        $bank = BankAccount::factory()->create();
        $check = $this->issueCheck($desk['treasurer'], $this->approvedVoucher($desk, '21.00'), $bank->id, '6002');

        $this->actingAs($desk['treasurer'])->get(route('finance.checks.print', $check))->assertInertia(fn (Assert $page) => $page
            ->where('check.amount_in_words', 'TWENTY ONE PESOS ONLY'));
    }

    public function test_check_print_is_hidden_when_the_flag_is_off(): void
    {
        $desk = $this->desk();
        $this->turnOff(FeatureFlag::CheckPrinting, $desk['barangay']);
        $check = Check::factory()->create();

        $this->actingAs($desk['treasurer'])->get(route('finance.checks.print', $check))->assertNotFound();
    }
}
