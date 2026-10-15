<?php

namespace App\Domain\Forms;

final class FieldKey
{
    public const int MAX_LENGTH = 40;

    public const string PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

    private const string FALLBACK_PREFIX = 'f_';

    private const string SEPARATOR = '_';

    public static function isValid(string $key): bool
    {
        return preg_match(self::PATTERN, $key) === 1;
    }

    public static function fromText(string $text): string
    {
        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', self::SEPARATOR, trim($text)));
        $slug = trim($slug, self::SEPARATOR);

        if ($slug === '' || ! ctype_alpha($slug[0])) {
            $slug = self::FALLBACK_PREFIX.$slug;
        }

        return rtrim(substr($slug, 0, self::MAX_LENGTH), self::SEPARATOR);
    }
}
