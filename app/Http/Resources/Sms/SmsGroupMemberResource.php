<?php

namespace App\Http\Resources\Sms;

use App\Models\SmsGroupMember;
use App\Services\Sms\SmsRecipientResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SmsGroupMember
 */
class SmsGroupMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resident = $this->resident;

        return [
            'id' => $this->id,
            'resident' => $resident === null ? null : [
                'id' => $resident->id,
                'resident_number' => $resident->resident_number,
                'full_name' => $resident->full_name,
                'is_removed' => $resident->deleted_at !== null,
                'is_deceased' => $resident->is_deceased,
            ],
            'label' => $this->label,
            'number' => $resident === null ? $this->mobile_number : SmsRecipientResolver::reachable($resident),
            'is_reachable' => $resident === null ? $this->mobile_number !== null : SmsRecipientResolver::reachable($resident) !== null,
        ];
    }
}
