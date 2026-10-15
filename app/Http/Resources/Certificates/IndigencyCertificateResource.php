<?php

namespace App\Http\Resources\Certificates;

use App\Http\Resources\Documents\PresentsIssuedDocument;
use App\Models\IndigencyCertificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IndigencyCertificate
 */
class IndigencyCertificateResource extends JsonResource
{
    use PresentsIssuedDocument;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'certificate_number' => $this->certificate_number,
            'resident' => $this->whenLoaded('resident', fn () => $this->residentSummary($this->resident)),
            'purpose_id' => $this->purpose_id,
            'purpose' => $this->whenLoaded('purpose', fn () => $this->purpose?->label),
            ...$this->lifecycle($this->resource),
        ];
    }
}
