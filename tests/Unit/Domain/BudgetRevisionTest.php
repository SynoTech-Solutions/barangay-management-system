<?php

namespace Tests\Unit\Domain;

use App\Domain\Finance\BudgetRevision;
use App\Domain\Finance\BudgetTotals;
use App\Enums\AccountGroupType;
use PHPUnit\Framework\TestCase;

class BudgetRevisionTest extends TestCase
{
    public function test_deltas_list_only_changed_accounts_including_new_and_removed_ones(): void
    {
        $previous = [1 => 10_000, 2 => 5_000, 3 => 2_000];
        $next = [1 => 8_000, 2 => 5_000, 4 => 4_000];

        $this->assertSame([1 => -2_000, 3 => -2_000, 4 => 4_000], BudgetRevision::deltas($previous, $next));
        $this->assertSame(0, BudgetRevision::imbalance($previous, $next));
    }

    public function test_imbalance_is_the_change_of_the_total(): void
    {
        $this->assertSame(1, BudgetRevision::imbalance([1 => 100], [1 => 101]));
        $this->assertSame([], BudgetRevision::deltas([1 => 100], [1 => 100]));
    }

    public function test_unappropriated_balance_is_income_minus_expense_and_ignores_other_groups(): void
    {
        $totals = BudgetTotals::of([
            [AccountGroupType::Income, 500_000],
            [AccountGroupType::Income, 250_000],
            [AccountGroupType::Expense, 600_000],
            [AccountGroupType::Other, 99_999],
        ]);

        $this->assertSame(750_000, $totals->income);
        $this->assertSame(600_000, $totals->expense);
        $this->assertSame(150_000, $totals->unappropriated());
        $this->assertSame(-100, BudgetTotals::of([[AccountGroupType::Expense, 100]])->unappropriated());
    }
}
