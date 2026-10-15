<?php

namespace App\Queries;

use App\Models\Business;
use App\Models\BusinessClearance;
use App\Models\Resident;

final readonly class BusinessClearancePrintQuery
{
    public function __construct(private DocumentPrintContext $context) {}

    /**
     * @return array<string, mixed>
     */
    public function data(BusinessClearance $clearance): array
    {
        $business = Business::query()->with(['nature:id,label', 'ownershipType:id,label'])->findOrFail($clearance->business_id);
        $owner = $clearance->owner_resident_id === null ? null : Resident::query()->withTrashed()->find($clearance->owner_resident_id);

        return [
            'document' => [
                'id' => $clearance->id,
                'clearance_number' => $clearance->clearance_number,
                'control_number' => $clearance->control_number,
                'issued_on' => $clearance->issued_at->format(DocumentPrintContext::DATE_FORMAT),
                'expires_on' => $clearance->expires_on->format(DocumentPrintContext::DATE_FORMAT),
                ...$this->context->payment($clearance, $clearance->doc_stamp),
                ...$this->context->printMarks($clearance),
            ],
            'business' => [
                'business_number' => $business->business_number,
                'name' => $clearance->business_name,
                'address' => $clearance->business_address,
                'nature' => $business->nature?->label,
                'ownership_type' => $business->ownershipType?->label,
            ],
            'owner' => [
                'name' => $clearance->owner_name,
                'address' => $owner === null ? null : $this->context->residentAddress($owner),
                'resident_id' => $owner?->id,
                'has_photo' => $owner === null ? false : $this->context->residentFacts($owner)['has_photo'],
                'photo_version' => $owner?->photo_taken_at?->getTimestamp(),
            ],
            'header' => $this->context->header(),
            'certifier' => $this->context->certifier($clearance),
        ];
    }
}
