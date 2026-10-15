<?php

namespace App\Http\Resources\Blotter;

use App\Enums\BlotterPartyRole;
use App\Models\Blotter;
use App\Models\BlotterParty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Blotter
 */
class BlotterListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $count = fn (BlotterPartyRole $role): int => $this->parties->filter(fn (BlotterParty $party): bool => $party->role === $role)->count();

        return [
            'id' => $this->id,
            'blotter_number' => $this->blotter_number,
            'incident_at' => $this->incident_at->toIso8601String(),
            'incident_type' => $this->incidentType?->label,
            'status' => $this->status->value,
            'status_label' => __($this->status->labelKey()),
            'complainant_count' => $count(BlotterPartyRole::Complainant),
            'respondent_count' => $count(BlotterPartyRole::Respondent),
        ];
    }
}
