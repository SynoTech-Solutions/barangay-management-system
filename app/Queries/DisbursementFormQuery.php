<?php

namespace App\Queries;

use App\Domain\Money\Centavos;
use App\Enums\AccountGroupType;
use App\Enums\BudgetStatus;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\Vendor;

final readonly class DisbursementFormQuery
{
    public function __construct(private BudgetSummaryQuery $summary) {}

    /**
     * @return list<array{value: int, label: string, year: int}>
     */
    public function budgets(): array
    {
        return array_values(Budget::query()
            ->where('status', BudgetStatus::Approved->value)
            ->orderByDesc('fiscal_year')
            ->get(['id', 'fiscal_year', 'title'])
            ->map(fn (Budget $budget): array => ['value' => $budget->id, 'label' => "{$budget->fiscal_year} · {$budget->title}", 'year' => $budget->fiscal_year])
            ->all());
    }

    /**
     * @param  array<int, int>  $keep
     * @return list<array{value: int, label: string, available: string}>
     */
    public function fundSources(?Budget $budget, array $keep = []): array
    {
        if ($budget === null) {
            return [];
        }

        $sources = [];
        foreach ($this->summary->for($budget)['rows'] as $row) {
            $usable = $row['is_posting'] && $row['group_type'] !== AccountGroupType::Income->value;
            $positive = Centavos::fromDecimal((string) $row['available']) > 0;
            if ($usable && ($positive || in_array($row['id'], $keep, true))) {
                $sources[] = ['value' => (int) $row['id'], 'label' => "{$row['code']} · {$row['name']}", 'available' => (string) $row['available']];
            }
        }

        return $sources;
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function vendors(?int $includeId = null): array
    {
        return array_values(Vendor::query()
            ->where(fn ($query) => $query->where('is_active', true)->when($includeId !== null, fn ($inner) => $inner->orWhere('id', $includeId)))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Vendor $vendor): array => ['value' => $vendor->id, 'label' => $vendor->name])
            ->all());
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function bankAccounts(?int $includeId = null): array
    {
        return array_values(BankAccount::query()
            ->where(fn ($query) => $query->where('is_active', true)->when($includeId !== null, fn ($inner) => $inner->orWhere('id', $includeId)))
            ->orderBy('depository_bank')
            ->orderBy('account_number')
            ->get(['id', 'account_number', 'account_name', 'depository_bank'])
            ->map(fn (BankAccount $account): array => ['value' => $account->id, 'label' => "{$account->depository_bank} · {$account->account_number} · {$account->account_name}"])
            ->all());
    }
}
