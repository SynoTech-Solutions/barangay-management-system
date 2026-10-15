<?php

namespace App\Http\Resources\Residents;

use App\Domain\Residents\ResidentAge;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Resident
 */
class ResidentOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'resident_number' => $this->resident_number,
            'full_name' => $this->full_name,
            'birthdate' => $this->birthdate?->toDateString(),
            'age' => ResidentAge::years($this->birthdate, CarbonImmutable::today()),
            'purok' => $this->whenLoaded('purok', fn () => $this->purok?->name),
            'is_deceased' => $this->is_deceased,
        ];
    }
}
