<?php

namespace App\Http\Resources\Certificates;

use App\Domain\Money\Centavos;
use App\Http\Resources\Documents\PresentsIssuedDocument;
use App\Models\BarangayClearance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BarangayClearance
 */
class BarangayClearanceResource extends JsonResource
{
    use PresentsIssuedDocument;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clearance_number' => $this->clearance_number,
            'control_number' => $this->control_number,
            'resident' => $this->whenLoaded('resident', fn () => $this->residentSummary($this->resident)),
            'clearance_type_id' => $this->clearance_type_id,
            'clearance_type' => $this->whenLoaded('clearanceType', fn () => $this->clearanceType?->label),
            'purpose_id' => $this->purpose_id,
            'purpose' => $this->whenLoaded('purpose', fn () => $this->purpose?->label),
            'doc_stamp' => $this->doc_stamp,
            'total' => Centavos::sum($this->amount, $this->doc_stamp),
            'expires_on' => $this->expires_on->toDateString(),
            ...$this->lifecycle($this->resource),
            ...$this->payment($this->resource),
        ];
    }
}
