<?php

namespace App\Services\Forms\Pdf;

use App\Enums\ErrorCode;
use App\Exceptions\BmsException;

final readonly class UnconfiguredPdfFormFiller implements PdfFormFiller
{
    public function fill(string $pdf, array $values): string
    {
        throw BmsException::of(ErrorCode::PdfFillerNotConfigured);
    }
}
