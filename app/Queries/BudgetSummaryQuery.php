<?php

namespace App\Queries;

use App\Domain\Finance\AccountTree;
use App\Domain\Finance\FundBalance;
use App\Domain\Money\Centavos;
use App\Enums\AccountGroupType;
use App\Models\Budget;
use App\Services\Finance\FundGuard;

final readonly class BudgetSummaryQuery
{
    public function __construct(
        private ChartOfAccountsQuery $chart,
        private FundGuard $funds,
    ) {}

    /**
     * @return array{rows: list<array<string, mixed>>, totals: array{appropriation: string, consumed: string, available: string}}
     */
    public function for(Budget $budget): array
    {
        $accounts = $this->chart->tree();
        $types = $this->chart->groupTypes($accounts);
        $balances = $this->funds->balances($budget);

        $parents = [];
        foreach ($accounts as $account) {
            $parents[$account->id] = $account->parent_id;
        }
        $appropriated = AccountTree::rollUp($parents, array_map(fn (FundBalance $balance): int => $balance->appropriation, $balances));
        $consumed = AccountTree::rollUp($parents, array_map(fn (FundBalance $balance): int => $balance->consumed, $balances));

        $rows = [];
        foreach ($accounts as $account) {
            $rows[] = [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'level' => $account->level,
                'kind' => $account->kind->value,
                'group_type' => $types[$account->id]->value,
                'is_posting' => ChartOfAccountsQuery::isPosting($account),
                'appropriation' => Centavos::toDecimal($appropriated[$account->id] ?? 0),
                'consumed' => Centavos::toDecimal($consumed[$account->id] ?? 0),
                'available' => Centavos::toDecimal(($appropriated[$account->id] ?? 0) - ($consumed[$account->id] ?? 0)),
                'is_overdrawn' => isset($balances[$account->id]) && $balances[$account->id]->isOverdrawn(),
                'is_zero' => ($appropriated[$account->id] ?? 0) === 0 && ($consumed[$account->id] ?? 0) === 0,
            ];
        }

        $expense = array_filter($balances, fn (FundBalance $balance, int $accountId): bool => ($types[$accountId] ?? null) === AccountGroupType::Expense, ARRAY_FILTER_USE_BOTH);
        $totalAppropriation = array_sum(array_map(fn (FundBalance $balance): int => $balance->appropriation, $expense));
        $totalConsumed = array_sum(array_map(fn (FundBalance $balance): int => $balance->consumed, $expense));

        return [
            'rows' => $rows,
            'totals' => [
                'appropriation' => Centavos::toDecimal($totalAppropriation),
                'consumed' => Centavos::toDecimal($totalConsumed),
                'available' => Centavos::toDecimal($totalAppropriation - $totalConsumed),
            ],
        ];
    }
}
