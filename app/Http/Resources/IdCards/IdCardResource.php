<?php

namespace App\Http\Resources\IdCards;

use App\Http\Resources\Documents\PresentsIssuedDocument;
use App\Models\IdCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IdCard
 */
class IdCardResource extends JsonResource
{
    use PresentsIssuedDocument;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'card_number' => $this->card_number,
            'resident' => $this->whenLoaded('resident', fn () => $this->residentSummary($this->resident)),
            ...$this->lifecycle($this->resource),
            ...$this->payment($this->resource),
        ];
    }
}
