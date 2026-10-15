<?php

namespace App\Http\Resources\Blotter;

use App\Models\BlotterParty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BlotterParty
 */
class BlotterPartyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'role_label' => __($this->role->labelKey()),
            'resident_id' => $this->resident_id,
            'resident' => $this->resident === null ? null : [
                'id' => $this->resident->id,
                'full_name' => $this->resident->full_name,
                'resident_number' => $this->resident->resident_number,
                'birthdate' => $this->resident->birthdate?->toDateString(),
                'age' => null,
                'is_deceased' => $this->resident->is_deceased,
            ],
            'name' => $this->name,
            'address' => $this->address,
            'display_name' => $this->displayName(),
        ];
    }
}
