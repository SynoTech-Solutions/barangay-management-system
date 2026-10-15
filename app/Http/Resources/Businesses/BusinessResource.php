<?php

namespace App\Http\Resources\Businesses;

use App\Models\Business;
use App\Queries\ExpiredBusinessQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Business
 */
class BusinessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lastExpiry = $this->getAttribute(ExpiredBusinessQuery::LAST_EXPIRY_COLUMN);

        return [
            'id' => $this->id,
            'business_number' => $this->business_number,
            'name' => $this->name,
            'owner_resident_id' => $this->owner_resident_id,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner === null ? null : [
                'id' => $this->owner->id,
                'full_name' => $this->owner->full_name,
                'resident_number' => $this->owner->resident_number,
                'is_removed' => $this->owner->deleted_at !== null,
            ]),
            'owner_name' => $this->owner_name,
            'owner_display_name' => $this->relationLoaded('owner') ? $this->ownerDisplayName() : $this->owner_name,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category?->label),
            'nature_id' => $this->nature_id,
            'nature' => $this->whenLoaded('nature', fn () => $this->nature?->label),
            'ownership_type_id' => $this->ownership_type_id,
            'ownership_type' => $this->whenLoaded('ownershipType', fn () => $this->ownershipType?->label),
            'purok_id' => $this->purok_id,
            'purok' => $this->whenLoaded('purok', fn () => $this->purok?->name),
            'street_address' => $this->street_address,
            'plate_number' => $this->plate_number,
            'operating_since' => $this->operating_since?->toDateString(),
            'capitalization' => $this->capitalization,
            'gross_receipts' => $this->gross_receipts,
            'status' => $this->status->value,
            'status_label' => __($this->status->labelKey()),
            'is_active' => $this->isActive(),
            'last_expires_on' => is_string($lastExpiry) ? $lastExpiry : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
