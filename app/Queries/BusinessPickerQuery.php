<?php

namespace App\Queries;

use App\Http\Resources\Businesses\BusinessOptionResource;
use App\Models\Business;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class BusinessPickerQuery
{
    private const array RELATIONS = ['owner:id,full_name', 'purok:id,name'];

    public function __construct(private BmsSettings $settings) {}

    /**
     * @return array<string, mixed>|null
     */
    public function option(?int $businessId): ?array
    {
        $business = $businessId === null ? null : Business::query()->with(self::RELATIONS)->find($businessId);

        return $business === null ? null : (new BusinessOptionResource($business))->resolve();
    }

    /**
     * @return LengthAwarePaginator<int, Business>
     */
    public function search(string $term, ?int $perPage): LengthAwarePaginator
    {
        return Business::query()
            ->with(self::RELATIONS)
            ->active()
            ->when($term === '', fn (Builder $query) => $query->whereRaw('false'))
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereRaw('name ilike ?', [Like::contains($term)])
                ->orWhereRaw('business_number ilike ?', [Like::startsWith($term)])))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }
}
