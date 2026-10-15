<?php

namespace Tests\Support;

use App\Services\Forms\Pdf\PdfFormFiller;

final class FakePdfFormFiller implements PdfFormFiller
{
    public const string OUTPUT = "%PDF-1.7\n%fake filled form\n";

    /** @var list<array<string, string|bool>> */
    public array $fills = [];

    public function fill(string $pdf, array $values): string
    {
        $this->fills[] = $values;

        return self::OUTPUT;
    }
}
