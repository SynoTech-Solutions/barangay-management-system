<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
final class BarangayScope implements Scope
{
    public const string COLUMN = 'barangay_id';

    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $barangay = app(CurrentBarangay::class)->require();

        $builder->where($model->qualifyColumn(self::COLUMN), $barangay->id);
    }
}
