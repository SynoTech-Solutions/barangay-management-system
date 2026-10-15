<?php

namespace Tests\Concerns;

use App\Enums\SystemRole;
use App\Enums\VoucherStatus;
use App\Models\Account;
use App\Models\Barangay;
use App\Models\Budget;
use App\Models\Check;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Finance\BudgetService;
use App\Services\Finance\ChartOfAccounts;

trait CreatesFinance
{
    /**
     * @return array{0: User, 1: Barangay}
     */
    protected function financeDesk(SystemRole $role = SystemRole::Treasurer, ?Barangay $barangay = null): array
    {
        $user = $this->barangayUser($role, $barangay);
        $barangay = $user->barangay;
        $this->assertNotNull($barangay);
        $this->inBarangay($barangay);

        return [$user, $barangay];
    }

    protected function installChart(Barangay $barangay): void
    {
        app(ChartOfAccounts::class)->installStandard($barangay);
        $this->inBarangay($barangay);
    }

    protected function account(string $code): Account
    {
        return Account::query()->where('code', $code)->firstOrFail();
    }

    /**
     * @param  array<string, string>  $amounts
     */
    protected function approvedBudget(User $actor, int $year, array $amounts): Budget
    {
        $budgets = app(BudgetService::class);
        $budget = $budgets->create($year, "Budget {$year}", $actor);
        $lines = [];
        foreach ($amounts as $code => $amount) {
            $lines[$this->account($code)->id] = $amount;
        }
        $budgets->prepare($budget, $lines, $actor);

        return $budgets->approve($budget, $actor);
    }

    /**
     * @param  array<string, string>  $items
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function voucherPayload(Budget $budget, array $items, array $overrides = []): array
    {
        return [
            'budget_id' => $budget->id,
            'payment_type' => 'reimbursement',
            'voucher_date' => "{$budget->fiscal_year}-03-15",
            'payee_name' => 'Sample  Payee',
            'particulars' => 'Office needs for the quarter',
            'items' => array_values(array_map(
                fn (string $code, string $amount): array => ['account_id' => $this->account($code)->id, 'description' => "Charge to {$code}", 'amount' => $amount],
                array_keys($items),
                $items,
            )),
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, string>  $items
     * @param  array<string, mixed>  $overrides
     */
    protected function preparedVoucher(User $preparer, Budget $budget, array $items, array $overrides = []): Voucher
    {
        $barangay = $preparer->barangay;
        $this->assertNotNull($barangay);
        $this->actingAs($preparer)->post(route('finance.vouchers.store'), $this->voucherPayload($budget, $items, $overrides))->assertSessionHasNoErrors();
        $this->inBarangay($barangay);

        return Voucher::query()->latest('id')->firstOrFail();
    }

    protected function verifiedVoucher(Voucher $voucher, User $verifier): Voucher
    {
        $this->actingAs($verifier)->post(route('finance.vouchers.verify', $voucher))->assertSessionHasNoErrors();
        $this->inBarangay($voucher->barangay()->firstOrFail());

        $voucher->refresh();
        $this->assertSame(VoucherStatus::Verified, $voucher->status);

        return $voucher;
    }

    protected function issueCheck(User $treasurer, Voucher $voucher, int $bankAccountId, string $number): Check
    {
        $this->actingAs($treasurer)->post(route('finance.vouchers.checks.store', $voucher), [
            'bank_account_id' => $bankAccountId,
            'check_number' => $number,
            'check_date' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->inBarangay($voucher->barangay()->firstOrFail());

        return Check::query()->where('check_number', $number)->whereNull('cancelled_at')->firstOrFail();
    }
}
