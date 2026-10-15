<?php

namespace App\Http\Resources\Finance;

use App\Models\OtherCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OtherCollection
 */
class OtherCollectionResource extends JsonResource
{
    private const string DATE_FORMAT = 'Y-m-d';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'collection_number' => $this->collection_number,
            'collection_type_id' => $this->collection_type_id,
            'collection_type' => $this->relationLoaded('collectionType') ? $this->collectionType?->name : null,
            'resident_id' => $this->resident_id,
            'payer_name' => $this->payer_name,
            'or_number' => $this->or_number,
            'amount' => $this->amount,
            'paid_on' => $this->paid_on->format(self::DATE_FORMAT),
            'remarks' => $this->remarks,
            'recorded_by' => $this->relationLoaded('recorder') ? $this->recorder?->name : null,
            'recorded_at' => $this->recorded_at->toIso8601String(),
            'is_cancelled' => $this->isCancelled(),
            'cancelled_by' => $this->relationLoaded('canceller') ? $this->canceller?->name : null,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
        ];
    }
}
