<?php

namespace App\Queries;

use App\Models\ColorCategory;
use App\Models\Resident;
use App\Models\ResidentPoliticalTag;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class PoliticalTagQuery
{
    /**
     * @param  list<int>  $residentIds
     * @return array<int, array{name: string, color_hex: string}>
     */
    public function forResidents(array $residentIds): array
    {
        if ($residentIds === []) {
            return [];
        }

        return ResidentPoliticalTag::query()
            ->with('category:id,name,color_hex')
            ->whereIn('resident_id', $residentIds)
            ->get()
            ->mapWithKeys(fn (ResidentPoliticalTag $tag): array => [$tag->resident_id => ['name' => $tag->category->name, 'color_hex' => $tag->category->color_hex]])
            ->all();
    }

    /**
     * @return array<int, array{value: int, label: string, color_hex: string}>
     */
    public function activeCategories(): array
    {
        return ColorCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (ColorCategory $category): array => ['value' => $category->id, 'label' => $category->name, 'color_hex' => $category->color_hex])
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, color_hex: string, total: int}>
     */
    public function votersByColor(?int $purokId): array
    {
        return array_values(array_map(
            fn (object $row): array => ['name' => (string) $row->name, 'color_hex' => (string) $row->color_hex, 'total' => (int) $row->total],
            $this->voters()
                ->when($purokId !== null, fn (QueryBuilder $query) => $query->where('residents.purok_id', $purokId))
                ->selectRaw('color_categories.name, color_categories.color_hex, count(*) as total')
                ->groupBy('color_categories.id', 'color_categories.name', 'color_categories.color_hex')
                ->orderByDesc('total')
                ->orderBy('color_categories.name')
                ->get()
                ->all(),
        ));
    }

    /**
     * @return list<array{purok: string, colors: list<array{name: string, color_hex: string, total: int}>}>
     */
    public function votersByPurok(): array
    {
        $rows = $this->voters()
            ->join('puroks', 'puroks.id', '=', 'residents.purok_id')
            ->selectRaw('puroks.id as purok_id, puroks.name as purok, color_categories.name, color_categories.color_hex, count(*) as total')
            ->groupBy('puroks.id', 'puroks.name', 'color_categories.id', 'color_categories.name', 'color_categories.color_hex')
            ->orderBy('puroks.name')
            ->orderByDesc('total')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $key = (int) $row->purok_id;
            $grouped[$key] ??= ['purok' => (string) $row->purok, 'colors' => []];
            $grouped[$key]['colors'][] = ['name' => (string) $row->name, 'color_hex' => (string) $row->color_hex, 'total' => (int) $row->total];
        }

        return array_values($grouped);
    }

    private function voters(): QueryBuilder
    {
        return Resident::query()
            ->living()
            ->where('is_registered_voter', true)
            ->toBase()
            ->join('resident_political_tags', 'resident_political_tags.resident_id', '=', 'residents.id')
            ->join('color_categories', 'color_categories.id', '=', 'resident_political_tags.color_category_id');
    }
}
