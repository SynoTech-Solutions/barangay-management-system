<?php

namespace App\Queries;

use App\Models\Business;
use App\Queries\Filters\BusinessListFilters;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class BusinessListQuery
{
    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, Business>
     */
    public function paginate(BusinessListFilters $filters, ?int $perPage): LengthAwarePaginator
    {
        return $this->builder($filters)
            ->with(Business::RELATIONS)
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }

    /**
     * @return Builder<Business>
     */
    public function builder(BusinessListFilters $filters): Builder
    {
        return Business::query()
            ->when($filters->status !== null, fn (Builder $query) => $query->where('status', $filters->status))
            ->when($filters->categoryId !== null, fn (Builder $query) => $query->where('category_id', $filters->categoryId))
            ->when($filters->natureId !== null, fn (Builder $query) => $query->where('nature_id', $filters->natureId))
            ->when($filters->ownershipTypeId !== null, fn (Builder $query) => $query->where('ownership_type_id', $filters->ownershipTypeId))
            ->when($filters->purokId !== null, fn (Builder $query) => $query->where('purok_id', $filters->purokId))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereRaw('name ilike ?', [Like::contains($filters->search)])
                ->orWhereRaw('business_number ilike ?', [Like::startsWith($filters->search)])
                ->orWhereRaw('owner_name ilike ?', [Like::contains($filters->search)])
                ->orWhereHas('owner', fn (Builder $owner) => $owner->whereRaw('full_name ilike ?', [Like::contains($filters->search)]))));
    }
}
