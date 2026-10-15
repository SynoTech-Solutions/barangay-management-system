<?php

namespace App\Queries;

use App\Models\CollectionType;
use Illuminate\Database\Eloquent\Collection;

final readonly class CollectionTypeQuery
{
    /**
     * @return Collection<int, CollectionType>
     */
    public function all(): Collection
    {
        return CollectionType::query()->withCount('collections')->orderByDesc('is_active')->orderBy('name')->get();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function options(?int $includeId = null): array
    {
        return array_values(CollectionType::query()
            ->where(fn ($query) => $query->where('is_active', true)->when($includeId !== null, fn ($inner) => $inner->orWhere('id', $includeId)))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CollectionType $type): array => ['value' => $type->id, 'label' => $type->name])
            ->values()
            ->all());
    }
}
