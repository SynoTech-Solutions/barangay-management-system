<?php

namespace App\Http\Resources\Residents;

use App\Models\ResidentWorkHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ResidentWorkHistory
 */
class ResidentWorkHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company', fn () => $this->company->label),
            'position_id' => $this->position_id,
            'position' => $this->whenLoaded('position', fn () => $this->position->label),
            'employment_status_id' => $this->employment_status_id,
            'employment_status' => $this->whenLoaded('employmentStatus', fn () => $this->employmentStatus->label),
            'started_on' => $this->started_on?->toDateString(),
            'ended_on' => $this->ended_on?->toDateString(),
            'is_current' => $this->is_current,
        ];
    }
}
