<?php

namespace App\Http\Resources\Sms;

use App\Enums\SmsStatus;
use App\Models\SmsMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SmsMessage
 */
class SmsMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recipient' => $this->recipient,
            'resident' => $this->resident === null ? null : [
                'id' => $this->resident->id,
                'full_name' => $this->resident->full_name,
                'is_removed' => $this->resident->deleted_at !== null,
            ],
            'body' => $this->body,
            'segments' => $this->segments,
            'encoding' => $this->encoding->value,
            'status' => $this->status->value,
            'status_label' => __($this->status->labelKey()),
            'attempts' => $this->attempts,
            'last_error_code' => $this->last_error_code,
            'provider_message_id' => $this->provider_message_id,
            'queued_at' => $this->queued_at->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'queued_by' => $this->whenLoaded('queuedBy', fn () => $this->queuedBy?->name),
            'cancelled_by' => $this->whenLoaded('cancelledBy', fn () => $this->cancelledBy?->name),
            'source' => $this->whenLoaded('batch', fn () => $this->batch === null ? null : __($this->batch->source->labelKey())),
            'is_pruned' => $this->pruned_at !== null,
            'can_cancel' => $this->status === SmsStatus::Queued,
            'can_retry' => $this->status === SmsStatus::Failed && $this->pruned_at === null,
        ];
    }
}
