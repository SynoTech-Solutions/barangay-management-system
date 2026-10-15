<?php

namespace App\Services\Finance;

use App\Domain\Finance\FundBalance;
use App\Domain\Money\Centavos;
use App\Enums\ErrorCode;
use App\Exceptions\BmsException;
use App\Models\Account;
use App\Models\Budget;

final readonly class FundGuard
{
    public function __construct(private FundConsumption $consumption) {}

    public function lock(int $budgetId): Budget
    {
        return Budget::query()->lockForUpdate()->findOrFail($budgetId);
    }

    /**
     * @param  array<int, int>  $required
     */
    public function ensureCovered(Budget $budget, array $required): void
    {
        $balances = $this->balances($budget);

        foreach ($required as $accountId => $amount) {
            if ($amount <= 0) {
                continue;
            }

            $balance = $balances[$accountId] ?? FundBalance::of(null, []);
            if (! $balance->canCover($amount)) {
                throw BmsException::of(ErrorCode::InsufficientFunds, [
                    'code' => $this->code($accountId),
                    'available' => Centavos::toDecimal($balance->available()),
                    'amount' => Centavos::toDecimal($amount),
                ]);
            }
        }
    }

    /**
     * @return array<int, int>
     */
    public function committed(Budget $budget): array
    {
        return array_map(fn (FundBalance $balance): int => $balance->consumed, $this->balances($budget));
    }

    /**
     * @return array<int, FundBalance>
     */
    public function balances(Budget $budget): array
    {
        $appropriations = $budget->effectiveVersion()?->amounts() ?? [];
        $entries = $this->consumption->entriesByAccount($budget);

        $balances = [];
        foreach (array_keys($appropriations + $entries) as $accountId) {
            $balances[$accountId] = FundBalance::of($appropriations[$accountId] ?? null, $entries[$accountId] ?? []);
        }

        return $balances;
    }

    private function code(int $accountId): string
    {
        $code = Account::query()->whereKey($accountId)->value('code');

        return is_string($code) ? $code : (string) $accountId;
    }
}
