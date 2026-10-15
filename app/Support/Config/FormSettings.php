<?php

namespace App\Support\Config;

final readonly class FormSettings
{
    public function __construct(
        public ?string $qpdfBinary,
        public int $pdfFillTimeoutSeconds,
    ) {}
}
