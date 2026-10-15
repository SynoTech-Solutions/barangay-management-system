<?php

namespace App\Queries;

use App\Models\Cedula;
use App\Models\Resident;

final readonly class CedulaPrintQuery
{
    public function __construct(private DocumentPrintContext $context) {}

    /**
     * @return array<string, mixed>
     */
    public function data(Cedula $cedula): array
    {
        $resident = Resident::query()->withTrashed()->findOrFail($cedula->resident_id);

        return [
            'document' => [
                'id' => $cedula->id,
                'cedula_number' => $cedula->cedula_number,
                'form_number' => $cedula->form_number,
                'tax_year' => $cedula->tax_year,
                'place_of_issue' => $cedula->place_of_issue,
                'issued_on' => $cedula->issued_at->format(DocumentPrintContext::DATE_FORMAT),
                'breakdown' => $cedula->breakdown,
                'print_count' => $cedula->print_count,
                'is_reprint' => $cedula->isReprint(),
            ],
            'resident' => $this->context->residentFacts($resident),
            'header' => $this->context->header(),
            'collector' => ['name' => $cedula->certified_by_name, 'title' => $cedula->certified_by_title],
        ];
    }
}
