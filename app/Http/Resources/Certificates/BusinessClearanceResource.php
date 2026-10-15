<?php

namespace App\Http\Resources\Certificates;

use App\Domain\Certificates\ClearanceExpiry;
use App\Domain\Money\Centavos;
use App\Enums\IssuedDocumentStatus;
use App\Http\Resources\Documents\PresentsIssuedDocument;
use App\Models\BusinessClearance;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BusinessClearance
 */
class BusinessClearanceResource extends JsonResource
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
            'business_id' => $this->business_id,
            'business_name' => $this->business_name,
            'business_address' => $this->business_address,
            'owner_name' => $this->owner_name,
            'doc_stamp' => $this->doc_stamp,
            'total' => Centavos::sum($this->amount, $this->doc_stamp),
            'expires_on' => $this->expires_on->toDateString(),
            'is_expired' => $this->status === IssuedDocumentStatus::Released && ClearanceExpiry::isExpired($this->expires_on, CarbonImmutable::today()),
            ...$this->lifecycle($this->resource),
            ...$this->payment($this->resource),
        ];
    }
}
