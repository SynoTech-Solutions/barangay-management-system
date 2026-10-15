<?php

namespace App\Domain\Finance;

use App\Enums\AccountGroupType;

final readonly class BudgetTotals
{
    public function __construct(
        public int $income,
        public int $expense,
        public int $other,
    ) {}

    /**
     * @param  iterable<array{0: AccountGroupType, 1: int}>  $lines
     */
    public static function of(iterable $lines): self
    {
        $sums = [AccountGroupType::Income->value => 0, AccountGroupType::Expense->value => 0, AccountGroupType::Other->value => 0];
        foreach ($lines as [$type, $amount]) {
            $sums[$type->value] += $amount;
        }

        return new self($sums[AccountGroupType::Income->value], $sums[AccountGroupType::Expense->value], $sums[AccountGroupType::Other->value]);
    }

    public function unappropriated(): int
    {
        return $this->income - $this->expense;
    }
}
