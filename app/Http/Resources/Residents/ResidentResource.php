<?php

namespace App\Http\Resources\Residents;

use App\Domain\Residents\ResidentAge;
use App\Models\LookupValue;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Resident
 */
class ResidentResource extends JsonResource
{
    public const array RELATIONS = [
        'purok:id,name', 'spouse:id,full_name,resident_number', 'birthplace:id,name',
        'title:id,label', 'nationality:id,label', 'religion:id,label', 'bloodType:id,label', 'bodyType:id,label',
        'healthIssue:id,label', 'profession:id,label', 'skill:id,label', 'incomeBracket:id,label', 'residenceType:id,label',
        'sectors:id,label', 'creator:id,name', 'updater:id,name',
    ];

    private const array LOOKUP_RELATIONS = [
        'title_id' => 'title',
        'nationality_id' => 'nationality',
        'religion_id' => 'religion',
        'blood_type_id' => 'bloodType',
        'body_type_id' => 'bodyType',
        'health_issue_id' => 'healthIssue',
        'profession_id' => 'profession',
        'skill_id' => 'skill',
        'income_bracket_id' => 'incomeBracket',
        'residence_type_id' => 'residenceType',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $today = CarbonImmutable::today();
        $resident = $this->resource;

        $labels = [];
        foreach (self::LOOKUP_RELATIONS as $attribute => $relation) {
            $related = $resident->relationLoaded($relation) ? $resident->getRelation($relation) : null;
            $labels[$attribute] = $related instanceof LookupValue ? $related->label : null;
        }

        return [
            ...$resident->only(Resident::PROFILE_ATTRIBUTES),
            'id' => $this->id,
            'resident_number' => $this->resident_number,
            'full_name' => $this->full_name,
            'birthdate' => $this->birthdate?->toDateString(),
            'residing_since' => $this->residing_since?->toDateString(),
            'deceased_on' => $this->deceased_on?->toDateString(),
            'gender' => $this->gender?->value,
            'gender_label' => $this->gender === null ? null : __($this->gender->labelKey()),
            'civil_status' => $this->civil_status?->value,
            'civil_status_label' => $this->civil_status === null ? null : __($this->civil_status->labelKey()),
            'age' => ResidentAge::years($this->birthdate, $today),
            'is_senior' => ResidentAge::isSenior($this->birthdate, $today),
            'is_incomplete' => $this->is_incomplete,
            'labels' => $labels,
            'purok' => $this->whenLoaded('purok', fn () => $this->purok?->name),
            'birthplace' => $this->whenLoaded('birthplace', fn () => $this->birthplace === null ? null : ['id' => $this->birthplace->id, 'label' => $this->birthplace->name]),
            'spouse' => $this->whenLoaded('spouse', fn () => $this->spouse === null ? null : (new ResidentOptionResource($this->spouse))->resolve()),
            'sectors' => $this->whenLoaded('sectors', fn () => $this->sectors->map(fn (LookupValue $sector): array => ['id' => $sector->id, 'label' => $sector->label])->values()->all()),
            'has_photo' => $this->photo_path !== null,
            'photo_version' => $this->photo_taken_at?->getTimestamp(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'updated_by' => $this->whenLoaded('updater', fn () => $this->updater?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
