<?php

namespace App\Domain\Residents;

final class NameNormalizer
{
    private const string WHITESPACE = '/\s+/u';

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) preg_replace(self::WHITESPACE, ' ', $value));

        return $normalized === '' ? null : $normalized;
    }

    public static function fullName(string $lastName, string $firstName, ?string $middleName = null, ?string $extension = null): string
    {
        $given = implode(' ', array_filter(
            [self::normalize($firstName), self::normalize($middleName), self::normalize($extension)],
            fn (?string $part): bool => $part !== null,
        ));

        return self::normalize($lastName).', '.$given;
    }

    public static function key(string $value): string
    {
        return mb_strtolower(self::normalize($value) ?? '');
    }
}
