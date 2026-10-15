<?php

namespace App\Services\Residents;

use App\Models\FamilyMember;
use App\Models\Resident;
use App\Models\User;

final readonly class FamilyMemberService
{
    public function __construct(private ResidentDetailRecords $records) {}

    /**
     * @param  array{relationship_id: int, full_name: string|null, birthdate: string|null, related_resident_id: int|null}  $attributes
     */
    public function add(Resident $resident, array $attributes, User $actor): FamilyMember
    {
        return $this->records->create($resident, new FamilyMember, $this->resolve($attributes), $actor);
    }

    /**
     * @param  array{relationship_id: int, full_name: string|null, birthdate: string|null, related_resident_id: int|null}  $attributes
     */
    public function update(FamilyMember $member, array $attributes, User $actor): FamilyMember
    {
        return $this->records->update($member, $this->resolve($attributes), $actor);
    }

    public function remove(FamilyMember $member, User $actor): void
    {
        $this->records->remove($member, $actor);
    }

    /**
     * @param  array{relationship_id: int, full_name: string|null, birthdate: string|null, related_resident_id: int|null}  $attributes
     * @return array<string, mixed>
     */
    private function resolve(array $attributes): array
    {
        if ($attributes['related_resident_id'] === null) {
            return $attributes;
        }

        $related = Resident::query()->findOrFail($attributes['related_resident_id']);

        return [
            ...$attributes,
            'full_name' => $related->full_name,
            'birthdate' => $related->birthdate?->toDateString(),
        ];
    }
}
