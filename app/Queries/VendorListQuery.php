<?php

namespace App\Queries;

use App\Models\Vendor;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class VendorListQuery
{
    public const int PRINT_LIMIT = 1000;

    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, Vendor>
     */
    public function paginate(string $search, ?int $perPage): LengthAwarePaginator
    {
        return $this->builder($search)->paginate($this->settings->pagination->clamp($perPage))->withQueryString();
    }

    /**
     * @return Collection<int, Vendor>
     */
    public function forPrint(string $search): Collection
    {
        return $this->builder($search)->where('is_active', true)->limit(self::PRINT_LIMIT)->get();
    }

    /**
     * @return Builder<Vendor>
     */
    private function builder(string $search): Builder
    {
        return Vendor::query()
            ->when($search !== '', fn (Builder $query) => $query->whereRaw('name ilike ?', [Like::contains($search)]))
            ->orderByDesc('is_active')
            ->orderBy('name');
    }
}
