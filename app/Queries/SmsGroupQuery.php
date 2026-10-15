<?php

namespace App\Queries;

use App\Models\SmsGroup;
use App\Models\SmsGroupMember;
use Illuminate\Database\Eloquent\Collection;

final readonly class SmsGroupQuery
{
    /**
     * @return Collection<int, SmsGroup>
     */
    public function all(): Collection
    {
        return SmsGroup::query()->withCount('members')->orderByDesc('is_active')->orderBy('name')->get();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public function activeOptions(): array
    {
        return array_values(SmsGroup::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (SmsGroup $group): array => ['value' => $group->id, 'label' => $group->name])
            ->all());
    }

    /**
     * @return Collection<int, SmsGroupMember>
     */
    public function members(SmsGroup $group): Collection
    {
        return SmsGroupMember::query()
            ->where('sms_group_id', $group->id)
            ->with(['resident' => fn ($query) => $query->select(['id', 'resident_number', 'full_name', 'mobile_number', 'is_deceased', 'deleted_at'])])
            ->orderBy('id')
            ->get();
    }
}
