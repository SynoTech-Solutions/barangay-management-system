<?php

namespace App\Domain\Residents;

final class PhilippineMobileNumber
{
    public const string PATTERN = '/^(?:\+?63|0)?(9\d{9})$/';

    private const string SEPARATORS = '/[\s().-]/';

    private const string NATIONAL_PREFIX = '0';

    public const string COUNTRY_CODE = '+63';

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $compact = (string) preg_replace(self::SEPARATORS, '', $value);

        if (preg_match(self::PATTERN, $compact, $matches) !== 1) {
            return null;
        }

        return self::NATIONAL_PREFIX.$matches[1];
    }

    public static function toE164(?string $value): ?string
    {
        $national = self::normalize($value);

        return $national === null ? null : self::COUNTRY_CODE.substr($national, strlen(self::NATIONAL_PREFIX));
    }

    public static function isValid(?string $value): bool
    {
        return self::normalize($value) !== null;
    }
}
