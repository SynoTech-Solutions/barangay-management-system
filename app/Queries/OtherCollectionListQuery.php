<?php

namespace App\Queries;

use App\Models\OtherCollection;
use App\Queries\Filters\OtherCollectionFilters;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class OtherCollectionListQuery
{
    public const int PRINT_LIMIT = 1000;

    private const string ZERO = '0.00';

    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, OtherCollection>
     */
    public function paginate(OtherCollectionFilters $filters, ?int $perPage): LengthAwarePaginator
    {
        return $this->builder($filters)->paginate($this->settings->pagination->clamp($perPage))->withQueryString();
    }

    /**
     * @return Collection<int, OtherCollection>
     */
    public function forPrint(OtherCollectionFilters $filters): Collection
    {
        return $this->builder($filters)->limit(self::PRINT_LIMIT)->get();
    }

    public function count(OtherCollectionFilters $filters): int
    {
        return $this->builder($filters)->reorder()->count();
    }

    public function total(OtherCollectionFilters $filters): string
    {
        $total = $this->builder($filters)->notCancelled()->reorder()->toBase()
            ->selectRaw('coalesce(sum(amount), 0)::numeric(14, 2)::text as total')
            ->value('total');

        return is_string($total) ? $total : self::ZERO;
    }

    /**
     * @return Builder<OtherCollection>
     */
    private function builder(OtherCollectionFilters $filters): Builder
    {
        return OtherCollection::query()
            ->with(['collectionType:id,name', 'recorder:id,name', 'canceller:id,name'])
            ->when(! $filters->includeCancelled, fn (Builder $query) => $query->notCancelled())
            ->when($filters->collectionTypeId !== null, fn (Builder $query) => $query->where('collection_type_id', $filters->collectionTypeId))
            ->when($filters->from !== null, fn (Builder $query) => $query->where('paid_on', '>=', $filters->from?->toDateString()))
            ->when($filters->to !== null, fn (Builder $query) => $query->where('paid_on', '<=', $filters->to?->toDateString()))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereRaw('payer_name ilike ?', [Like::contains($filters->search)])
                ->orWhereRaw('collection_number ilike ?', [Like::startsWith($filters->search)])
                ->orWhereRaw('or_number ilike ?', [Like::startsWith($filters->search)])))
            ->orderByDesc('paid_on')
            ->orderByDesc('id');
    }
}
