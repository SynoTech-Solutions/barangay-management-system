<?php

namespace App\Http\Resources\Documents;

use App\Models\IssuedDocument;
use App\Models\Resident;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JsonResource
 */
trait PresentsIssuedDocument
{
    /**
     * @return array<string, mixed>
     */
    protected function lifecycle(IssuedDocument $document): array
    {
        return [
            'status' => $document->status->value,
            'status_label' => __($document->status->labelKey()),
            'amount' => $document->amount,
            'verified_by_name' => $document->verified_by_name,
            'certified_by_name' => $document->certified_by_name,
            'issued_by' => $this->whenLoaded('issuer', fn () => $document->issuer?->name),
            'issued_at' => $document->issued_at->toIso8601String(),
            'released_by' => $this->whenLoaded('releaser', fn () => $document->releaser?->name),
            'released_at' => $document->released_at?->toIso8601String(),
            'cancelled_by' => $this->whenLoaded('canceller', fn () => $document->canceller?->name),
            'cancelled_at' => $document->cancelled_at?->toIso8601String(),
            'cancel_reason' => $document->cancel_reason,
            'print_count' => $document->print_count,
            'is_pending' => $document->isPending(),
        ];
    }

    /**
     * @return array{ctc_number: string|null, ctc_place: string|null, ctc_date: string|null, or_number: string|null}
     */
    protected function payment(IssuedDocument $document): array
    {
        return [
            'ctc_number' => $document->ctc_number,
            'ctc_place' => $document->ctc_place,
            'ctc_date' => $document->ctc_date?->toDateString(),
            'or_number' => $document->or_number,
        ];
    }

    /**
     * @return array{id: int, full_name: string, resident_number: string, is_removed: bool}|null
     */
    protected function residentSummary(?Resident $resident): ?array
    {
        return $resident === null ? null : [
            'id' => $resident->id,
            'full_name' => $resident->full_name,
            'resident_number' => $resident->resident_number,
            'is_removed' => $resident->deleted_at !== null,
        ];
    }
}
