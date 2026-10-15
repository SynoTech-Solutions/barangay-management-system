<?php

namespace App\Queries;

use App\Models\Business;
use App\Models\BusinessClearance;
use App\Support\Config\BmsSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class ExpiredBusinessQuery
{
    public const string LAST_EXPIRY_COLUMN = 'last_expires_on';

    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, Business>
     */
    public function paginate(CarbonImmutable $today, ?int $perPage): LengthAwarePaginator
    {
        return $this->builder($today)
            ->with(Business::RELATIONS)
            ->addSelect([self::LAST_EXPIRY_COLUMN => BusinessClearance::query()
                ->notCancelled()
                ->whereColumn('business_clearances.business_id', 'businesses.id')
                ->selectRaw('max(expires_on)')])
            ->orderBy(self::LAST_EXPIRY_COLUMN)
            ->orderBy('name')
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }

    /**
     * @return Builder<Business>
     */
    public function builder(CarbonImmutable $today): Builder
    {
        return Business::query()
            ->select('businesses.*')
            ->active()
            ->whereHas('clearances', fn (Builder $query) => $query->notCancelled())
            ->whereDoesntHave('clearances', fn (Builder $query) => $query->notCancelled()->where('expires_on', '>=', $today->toDateString()));
    }
}
