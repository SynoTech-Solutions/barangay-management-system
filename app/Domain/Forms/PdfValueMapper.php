<?php

namespace App\Domain\Forms;

use App\Enums\PdfFieldType;

final class PdfValueMapper
{
    public static function value(PdfFieldType $pdfType, string|int|bool|null $raw, string $display, ?string $checkedText, ?string $uncheckedText): string|bool
    {
        $checked = is_bool($raw) ? $raw : trim($display) !== '';

        if ($pdfType === PdfFieldType::Checkbox) {
            return $checked;
        }

        if (is_bool($raw)) {
            return $raw ? ($checkedText ?? $display) : ($uncheckedText ?? '');
        }

        return $display;
    }
}
