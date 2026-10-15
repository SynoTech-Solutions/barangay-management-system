<?php

namespace App\Services\Forms\Pdf;

interface PdfFormFiller
{
    /**
     * @param  array<string, string|bool>  $values
     */
    public function fill(string $pdf, array $values): string;
}
