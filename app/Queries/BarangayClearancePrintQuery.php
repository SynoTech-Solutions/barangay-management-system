<?php

namespace App\Queries;

use App\Enums\FeatureFlag;
use App\Models\BarangayClearance;
use App\Models\Resident;
use App\Services\Features\FeatureFlags;

final readonly class BarangayClearancePrintQuery
{
    public function __construct(
        private DocumentPrintContext $context,
        private FeatureFlags $features,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(BarangayClearance $clearance): array
    {
        $clearance->loadMissing(['clearanceType:id,label', 'purpose:id,label']);
        $resident = Resident::query()->withTrashed()->findOrFail($clearance->resident_id);

        return [
            'document' => [
                'id' => $clearance->id,
                'clearance_number' => $clearance->clearance_number,
                'control_number' => $clearance->control_number,
                'type' => $clearance->clearanceType?->label,
                'purpose' => $clearance->purpose?->label,
                'issued_on' => $clearance->issued_at->format(DocumentPrintContext::DATE_FORMAT),
                'expires_on' => $clearance->expires_on->format(DocumentPrintContext::DATE_FORMAT),
                ...$this->context->payment($clearance, $clearance->doc_stamp),
                ...$this->context->printMarks($clearance),
            ],
            'resident' => $this->context->residentFacts($resident),
            'header' => $this->context->header(),
            'verifier' => $this->features->enabled(FeatureFlag::SecretaryApprovalBlock) && $clearance->verified_by_name !== null
                ? ['name' => $clearance->verified_by_name, 'title' => $clearance->verified_by_title]
                : null,
            'certifier' => $this->context->certifier($clearance),
        ];
    }
}
