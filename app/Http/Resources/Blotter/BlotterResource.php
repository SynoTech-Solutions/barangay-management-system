<?php

namespace App\Http\Resources\Blotter;

use App\Models\Blotter;
use App\Models\BlotterPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Blotter
 */
class BlotterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'blotter_number' => $this->blotter_number,
            'incident_type_id' => $this->incident_type_id,
            'incident_type' => $this->incidentType?->label,
            'incident_at' => $this->incident_at->toIso8601String(),
            'place' => $this->place,
            'narrative' => $this->narrative,
            'status' => $this->status->value,
            'status_label' => __($this->status->labelKey()),
            'is_open' => $this->isOpen(),
            'reported_at' => $this->reported_at->toIso8601String(),
            'recorded_by' => $this->recorder?->name,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'resolved_by' => $this->resolver?->name,
            'resolution_notes' => $this->resolution_notes,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $this->canceller?->name,
            'cancel_reason' => $this->cancel_reason,
            'parties' => array_values(BlotterPartyResource::collection($this->parties)->resolve()),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn (BlotterPhoto $photo): array => [
                'id' => $photo->id,
                'created_at' => $photo->created_at?->toIso8601String(),
            ])->values()->all()),
        ];
    }
}
