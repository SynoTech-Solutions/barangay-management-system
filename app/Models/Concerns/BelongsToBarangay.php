<?php

namespace App\Models\Concerns;

use App\Models\Barangay;
use App\Tenancy\BarangayScope;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Model
 */
trait BelongsToBarangay
{
    public static function bootBelongsToBarangay(): void
    {
        static::addGlobalScope(new BarangayScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute(BarangayScope::COLUMN) === null) {
                $model->setAttribute(BarangayScope::COLUMN, app(CurrentBarangay::class)->require()->id);
            }
        });
    }

    /**
     * @return BelongsTo<Barangay, $this>
     */
    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    /**
     * @return Builder<static>
     */
    public static function acrossBarangays(): Builder
    {
        return static::query()->withoutGlobalScope(BarangayScope::class);
    }
}
