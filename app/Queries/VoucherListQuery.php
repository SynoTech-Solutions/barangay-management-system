<?php

namespace App\Queries;

use App\Enums\PaymentType;
use App\Enums\VoucherStatus;
use App\Models\Voucher;
use App\Models\VoucherItem;
use App\Queries\Filters\VoucherFilters;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class VoucherListQuery
{
    public const string UNLIQUIDATED_PAGE = 'unliquidated_page';

    private const string TOTAL_EXPRESSION = 'coalesce(sum(amount), 0)::numeric(14, 2)::text';

    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, Voucher>
     */
    public function paginate(VoucherFilters $filters, ?int $perPage): LengthAwarePaginator
    {
        return $this->withTotals(Voucher::query())
            ->with(['activeCheck:id,voucher_id,check_number,status', 'liquidation:id,voucher_id,liquidation_number'])
            ->when($filters->status !== null, fn (Builder $query) => $query->where('status', $filters->status?->value))
            ->when($filters->paymentType !== null, fn (Builder $query) => $query->where('payment_type', $filters->paymentType?->value))
            ->when($filters->from !== null, fn (Builder $query) => $query->where('voucher_date', '>=', $filters->from?->toDateString()))
            ->when($filters->to !== null, fn (Builder $query) => $query->where('voucher_date', '<=', $filters->to?->toDateString()))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereRaw('payee_name ilike ?', [Like::contains($filters->search)])
                ->orWhereRaw('voucher_number ilike ?', [Like::startsWith($filters->search)])
                ->orWhereRaw('particulars ilike ?', [Like::contains($filters->search)])))
            ->orderByDesc('voucher_date')
            ->orderByDesc('id')
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }

    /**
     * @return LengthAwarePaginator<int, Voucher>
     */
    public function unliquidated(string $search, ?int $perPage): LengthAwarePaginator
    {
        return $this->withTotals(Voucher::query())
            ->where('status', VoucherStatus::Released->value)
            ->where('payment_type', PaymentType::CashAdvance->value)
            ->whereDoesntHave('liquidation')
            ->with('activeCheck:id,voucher_id,check_number,status')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereRaw('payee_name ilike ?', [Like::contains($search)])
                ->orWhereRaw('voucher_number ilike ?', [Like::startsWith($search)])))
            ->orderBy('released_at')
            ->orderBy('id')
            ->paginate($this->settings->pagination->clamp($perPage), pageName: self::UNLIQUIDATED_PAGE)
            ->withQueryString();
    }

    public function find(int $id): Voucher
    {
        return $this->withTotals(Voucher::query())->findOrFail($id);
    }

    public function detail(int $id): Voucher
    {
        return $this->find($id)->load([
            'items.account:id,code,name',
            'activeCheck',
            'liquidation:id,voucher_id,liquidation_number',
            'preparer:id,name',
            'verifier:id,name',
            'approver:id,name',
            'canceller:id,name',
        ]);
    }

    /**
     * @param  Builder<Voucher>  $query
     * @return Builder<Voucher>
     */
    private function withTotals(Builder $query): Builder
    {
        return $query
            ->select('vouchers.*')
            ->selectSub(VoucherItem::query()->whereColumn('voucher_items.voucher_id', 'vouchers.id')->selectRaw(self::TOTAL_EXPRESSION), 'items_total');
    }
}
