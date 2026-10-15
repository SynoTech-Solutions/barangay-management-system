<?php

namespace App\Queries;

use App\Models\Liquidation;
use App\Queries\Filters\LiquidationFilters;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class LiquidationListQuery
{
    public function __construct(private BmsSettings $settings) {}

    public function detail(int $id): Liquidation
    {
        return Liquidation::query()->with(['voucher', 'items.voucherItem:id,amount', 'clearer:id,name'])->findOrFail($id);
    }

    /**
     * @return LengthAwarePaginator<int, Liquidation>
     */
    public function paginate(LiquidationFilters $filters, ?int $perPage): LengthAwarePaginator
    {
        return Liquidation::query()
            ->with(['voucher:id,voucher_number,payee_name,voucher_date', 'items.voucherItem:id,amount', 'clearer:id,name'])
            ->when($filters->status !== null, fn (Builder $query) => $query->where('status', $filters->status?->value))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereRaw('liquidation_number ilike ?', [Like::startsWith($filters->search)])
                ->orWhereHas('voucher', fn (Builder $voucher) => $voucher
                    ->whereRaw('voucher_number ilike ?', [Like::startsWith($filters->search)])
                    ->orWhereRaw('payee_name ilike ?', [Like::contains($filters->search)]))))
            ->orderByDesc('liquidated_on')
            ->orderByDesc('id')
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }
}
