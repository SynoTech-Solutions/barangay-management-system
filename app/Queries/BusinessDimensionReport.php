<?php

namespace App\Queries;

use App\Enums\BusinessDimension;
use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\LookupValue;
use App\Models\Purok;
use Illuminate\Database\Eloquent\Builder;

final readonly class BusinessDimensionReport
{
    public const string NOT_RECORDED = 'none';

    private const string TOTAL_COLUMN = 'total';

    /**
     * @return list<array{key: string, label: string, total: int}>
     */
    public function counts(BusinessDimension $dimension, ?BusinessStatus $status): array
    {
        $column = $dimension->column();

        $rows = Business::query()
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->toBase()
            ->select($column)
            ->selectRaw('count(*) as '.self::TOTAL_COLUMN)
            ->groupBy($column)
            ->get();

        $labels = $this->labels($dimension, array_values($rows->pluck($column)->filter(fn (mixed $key): bool => is_int($key))->all()));

        return array_values($rows
            ->map(function (object $row) use ($column, $labels): array {
                $key = $row->{$column} ?? null;

                return [
                    'key' => $key === null ? self::NOT_RECORDED : (string) $key,
                    'label' => $key === null ? (string) __('businesses.not_recorded') : ($labels[(string) $key] ?? (string) $key),
                    'total' => (int) $row->{self::TOTAL_COLUMN},
                ];
            })
            ->sortBy([['total', 'desc'], ['label', 'asc']])
            ->all());
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, string>
     */
    private function labels(BusinessDimension $dimension, array $ids): array
    {
        if ($dimension === BusinessDimension::Status) {
            $labels = [];
            foreach (BusinessStatus::cases() as $status) {
                $labels[$status->value] = (string) __($status->labelKey());
            }

            return $labels;
        }

        if ($dimension === BusinessDimension::Purok) {
            return $this->keyedByString(Purok::query()->whereKey($ids)->pluck('name', 'id')->all());
        }

        return $this->keyedByString(LookupValue::query()->whereKey($ids)->pluck('label', 'id')->all());
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return array<string, string>
     */
    private function keyedByString(array $values): array
    {
        $keyed = [];
        foreach ($values as $key => $value) {
            $keyed[(string) $key] = (string) $value;
        }

        return $keyed;
    }
}
