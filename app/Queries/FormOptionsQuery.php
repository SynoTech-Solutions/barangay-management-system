<?php

namespace App\Queries;

use App\Enums\LookupType;
use App\Models\LookupValue;
use App\Models\Purok;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Builder;

final readonly class FormOptionsQuery
{
    public function __construct(private CurrentBarangay $currentBarangay) {}

    /**
     * @param  list<LookupType>  $types
     * @param  list<int>  $keepIds
     * @return array<string, list<array{value: int, label: string}>>
     */
    public function lookups(array $types, array $keepIds = []): array
    {
        $values = LookupValue::query()
            ->whereIn('type', array_map(fn (LookupType $type): string => $type->value, $types))
            ->where(fn (Builder $query) => $query->whereNull('barangay_id')->orWhere('barangay_id', $this->currentBarangay->require()->id))
            ->where(fn (Builder $query) => $query->where('is_active', true)->when($keepIds !== [], fn (Builder $inner) => $inner->orWhereIn('id', $keepIds)))
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'type', 'label']);

        $lookups = [];
        foreach ($types as $type) {
            $lookups[$type->value] = [];
        }
        foreach ($values as $value) {
            $lookups[$value->type->value][] = ['value' => $value->id, 'label' => $value->label];
        }

        return $lookups;
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function puroks(?int $keepId = null): array
    {
        return array_values(Purok::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->when($keepId !== null, fn (Builder $inner) => $inner->orWhereKey($keepId)))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Purok $purok): array => ['value' => $purok->id, 'label' => $purok->name])
            ->all());
    }
}
