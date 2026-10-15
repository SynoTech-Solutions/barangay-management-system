<?php

namespace App\Queries;

use App\Models\Check;
use App\Queries\Filters\CheckFilters;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class CheckListQuery
{
    private const string ZERO = '0.00';

    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, Check>
     */
    public function paginate(CheckFilters $filters, ?int $perPage): LengthAwarePaginator
    {
        return $this->builder($filters)
            ->with(['voucher:id,voucher_number', 'bankAccount:id,account_number,account_name,depository_bank', 'issuer:id,name', 'releaser:id,name', 'canceller:id,name'])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }

    public function total(CheckFilters $filters): string
    {
        $total = $this->builder($filters)->whereNull('cancelled_at')->toBase()
            ->selectRaw('coalesce(sum(amount), 0)::numeric(14, 2)::text as total')
            ->value('total');

        return is_string($total) ? $total : self::ZERO;
    }

    /**
     * @return Builder<Check>
     */
    private function builder(CheckFilters $filters): Builder
    {
        return Check::query()
            ->when($filters->status !== null, fn (Builder $query) => $query->where('status', $filters->status?->value))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereRaw('payee_name ilike ?', [Like::contains($filters->search)])
                ->orWhereRaw('check_number ilike ?', [Like::startsWith($filters->search)])));
    }
}
