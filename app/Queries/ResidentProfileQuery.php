<?php

namespace App\Queries;

use App\Http\Resources\Residents\FamilyMemberResource;
use App\Http\Resources\Residents\ResidentEducationResource;
use App\Http\Resources\Residents\ResidentResource;
use App\Http\Resources\Residents\ResidentWorkHistoryResource;
use App\Models\Resident;

final class ResidentProfileQuery
{
    /**
     * @return array{resident: array<string, mixed>, family_members: list<array<string, mixed>>, educations: list<array<string, mixed>>|null, work_histories: list<array<string, mixed>>|null}
     */
    public function details(Resident $resident, bool $withWorkEducation): array
    {
        $resident->load(ResidentResource::RELATIONS);

        $familyMembers = $resident->familyMembers()
            ->with(['relationship:id,label', 'relatedResident:id,full_name,resident_number,birthdate,is_deceased'])
            ->orderBy('id')
            ->get();

        return [
            'resident' => (new ResidentResource($resident))->resolve(),
            'family_members' => array_values(FamilyMemberResource::collection($familyMembers)->resolve()),
            'educations' => $withWorkEducation
                ? array_values(ResidentEducationResource::collection(
                    $resident->educations()->with(['school:id,label', 'course:id,label'])->orderByRaw('started_on desc nulls last')->orderBy('id')->get(),
                )->resolve())
                : null,
            'work_histories' => $withWorkEducation
                ? array_values(ResidentWorkHistoryResource::collection(
                    $resident->workHistories()->with(['company:id,label', 'position:id,label', 'employmentStatus:id,label'])->orderByDesc('is_current')->orderByRaw('started_on desc nulls last')->orderBy('id')->get(),
                )->resolve())
                : null,
        ];
    }
}
