<?php

namespace App\Domain\Money;

final class OrNumber
{
    private const string WHITESPACE = '/\s+/u';

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtoupper(trim((string) preg_replace(self::WHITESPACE, ' ', $value)));

        return $normalized === '' ? null : $normalized;
    }
}
