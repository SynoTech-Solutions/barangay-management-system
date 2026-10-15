<?php

namespace App\Http\Resources\Residents;

use App\Domain\Residents\LengthOfStay;
use App\Domain\Residents\ResidentAge;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Resident
 */
class ResidentListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $today = CarbonImmutable::today();

        return [
            'id' => $this->id,
            'resident_number' => $this->resident_number,
            'full_name' => $this->full_name,
            'birthdate' => $this->birthdate?->toDateString(),
            'age' => ResidentAge::years($this->birthdate, $today),
            'is_senior' => ResidentAge::isSenior($this->birthdate, $today),
            'gender' => $this->gender?->value,
            'gender_label' => $this->gender === null ? null : __($this->gender->labelKey()),
            'civil_status_label' => $this->civil_status === null ? null : __($this->civil_status->labelKey()),
            'purok' => $this->whenLoaded('purok', fn () => $this->purok?->name),
            'mobile_number' => $this->mobile_number,
            'is_registered_voter' => $this->is_registered_voter,
            'is_pwd' => $this->is_pwd,
            'is_indigent' => $this->is_indigent,
            'is_single_parent' => $this->is_single_parent,
            'is_deceased' => $this->is_deceased,
            'is_incomplete' => $this->is_incomplete,
            'is_removed' => $this->deleted_at !== null,
            'residing_since' => $this->residing_since?->toDateString(),
            'length_of_stay' => ($stay = LengthOfStay::between($this->residing_since, $today)) === null ? null : ['years' => $stay->years, 'months' => $stay->months],
        ];
    }
}
