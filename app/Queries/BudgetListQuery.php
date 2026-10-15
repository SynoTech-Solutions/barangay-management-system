<?php

namespace App\Queries;

use App\Models\Budget;
use App\Support\Config\BmsSettings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final readonly class BudgetListQuery
{
    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, Budget>
     */
    public function paginate(?int $perPage): LengthAwarePaginator
    {
        return Budget::query()
            ->with('approver:id,name')
            ->orderByDesc('fiscal_year')
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }

    /**
     * @return Collection<int, Budget>
     */
    public function copySources(Budget $target): Collection
    {
        return Budget::query()
            ->whereKeyNot($target->id)
            ->whereNotNull('approved_at')
            ->orderByDesc('fiscal_year')
            ->get(['id', 'fiscal_year', 'title']);
    }
}
