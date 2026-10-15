<?php

namespace App\Queries;

use App\Domain\Finance\AccountTree;
use App\Domain\Finance\BudgetRevision;
use App\Domain\Finance\BudgetTotals;
use App\Domain\Money\Centavos;
use App\Enums\BudgetStatus;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetVersion;
use Carbon\CarbonImmutable;

final readonly class BudgetWorksheetQuery
{
    public function __construct(private ChartOfAccountsQuery $chart) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Budget $budget, bool $withUnappropriated): array
    {
        $accounts = $this->chart->tree();
        $types = $this->chart->groupTypes($accounts);
        $versions = $budget->versions()->with(['lines', 'creator:id,name'])->orderBy('number')->get();
        $current = $versions->last();
        $amounts = $current instanceof BudgetVersion ? $this->amountsOf($current) : [];
        $parents = array_combine(array_map(fn (Account $account): int => $account->id, $accounts), array_map(fn (Account $account): ?int => $account->parent_id, $accounts));
        $rolled = AccountTree::rollUp($parents, $amounts);
        $totals = BudgetTotals::of(array_map(fn (int $id, int $amount): array => [$types[$id], $amount], array_keys($amounts), $amounts));

        return [
            'budget' => $this->budget($budget),
            'version' => $current?->number,
            'rows' => array_map(fn (Account $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'level' => $account->level,
                'kind' => $account->kind->value,
                'group_type' => $types[$account->id]->value,
                'is_posting' => ChartOfAccountsQuery::isPosting($account),
                'is_active' => $account->is_active,
                'amount' => Centavos::toDecimal($amounts[$account->id] ?? 0),
                'total' => Centavos::toDecimal($rolled[$account->id] ?? 0),
            ], $accounts),
            'totals' => [
                'income' => Centavos::toDecimal($totals->income),
                'expense' => Centavos::toDecimal($totals->expense),
                'other' => Centavos::toDecimal($totals->other),
                'unappropriated' => $withUnappropriated ? Centavos::toDecimal($totals->unappropriated()) : null,
            ],
            'history' => $this->history(array_values($versions->all()), $accounts),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function budget(Budget $budget): array
    {
        return [
            'id' => $budget->id,
            'fiscal_year' => $budget->fiscal_year,
            'title' => $budget->title,
            'status' => $budget->status->value,
            'status_label' => __($budget->status->labelKey()),
            'is_draft' => $budget->status === BudgetStatus::Draft,
            'approved_at' => $budget->approved_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function amountsOf(BudgetVersion $version): array
    {
        $amounts = [];
        foreach ($version->lines as $line) {
            $amounts[$line->account_id] = Centavos::fromDecimal($line->amount);
        }

        return $amounts;
    }

    /**
     * @param  list<BudgetVersion>  $versions
     * @param  list<Account>  $accounts
     * @return list<array<string, mixed>>
     */
    private function history(array $versions, array $accounts): array
    {
        $byId = [];
        foreach ($accounts as $account) {
            $byId[$account->id] = $account;
        }

        $history = [];
        $previous = null;
        foreach ($versions as $version) {
            $amounts = $this->amountsOf($version);
            $remarks = $version->lines->pluck('remarks', 'account_id')->all();
            $changes = [];
            if ($previous !== null) {
                foreach (BudgetRevision::deltas($previous, $amounts) as $accountId => $delta) {
                    $changes[] = [
                        'code' => $byId[$accountId]->code ?? '',
                        'name' => $byId[$accountId]->name ?? '',
                        'previous' => Centavos::toDecimal($previous[$accountId] ?? 0),
                        'amount' => Centavos::toDecimal($amounts[$accountId] ?? 0),
                        'delta' => Centavos::toDecimal($delta),
                        'remarks' => $remarks[$accountId] ?? null,
                    ];
                }
            }
            usort($changes, fn (array $a, array $b): int => strnatcmp($a['code'], $b['code']));

            $history[] = [
                'number' => $version->number,
                'is_revision' => $version->isRevision(),
                'remarks' => $version->remarks,
                'created_by' => $version->creator?->name,
                'approved_at' => $version->approved_at instanceof CarbonImmutable ? $version->approved_at->toIso8601String() : null,
                'total' => Centavos::toDecimal(array_sum($amounts)),
                'changes' => $changes,
            ];
            $previous = $amounts;
        }

        return $history;
    }
}
