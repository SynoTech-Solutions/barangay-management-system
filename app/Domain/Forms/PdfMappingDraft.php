<?php

namespace App\Domain\Forms;

final readonly class PdfMappingDraft
{
    public function __construct(
        public string $pdfField,
        public ?string $token,
        public ?string $checkedValue,
        public ?string $uncheckedValue,
    ) {}
}
