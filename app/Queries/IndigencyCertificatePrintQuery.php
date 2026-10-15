<?php

namespace App\Queries;

use App\Models\IndigencyCertificate;
use App\Models\Resident;

final readonly class IndigencyCertificatePrintQuery
{
    public function __construct(private DocumentPrintContext $context) {}

    /**
     * @return array<string, mixed>
     */
    public function data(IndigencyCertificate $certificate): array
    {
        $certificate->loadMissing('purpose:id,label');
        $resident = Resident::query()->withTrashed()->findOrFail($certificate->resident_id);

        return [
            'document' => [
                'id' => $certificate->id,
                'certificate_number' => $certificate->certificate_number,
                'purpose' => $certificate->purpose?->label,
                'issued_on' => $certificate->issued_at->format(DocumentPrintContext::DATE_FORMAT),
                ...$this->context->printMarks($certificate),
            ],
            'resident' => $this->context->residentFacts($resident),
            'header' => $this->context->header(),
            'certifier' => $this->context->certifier($certificate),
        ];
    }
}
