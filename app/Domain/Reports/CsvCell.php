<?php

namespace App\Domain\Reports;

final class CsvCell
{
    private const string FORMULA_PREFIXES = '=+-@';

    private const string FORMULA_GUARD = "'";

    public static function safe(string $text): string
    {
        return $text !== '' && str_contains(self::FORMULA_PREFIXES, $text[0]) ? self::FORMULA_GUARD.$text : $text;
    }
}
