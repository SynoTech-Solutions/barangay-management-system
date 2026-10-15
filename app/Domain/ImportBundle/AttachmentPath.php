<?php

namespace App\Domain\ImportBundle;

final class AttachmentPath
{
    public const int MAX_LENGTH = 255;

    private const string SEPARATOR = '/';

    private const array FORBIDDEN_SEGMENTS = ['', '.', '..'];

    private const string FORBIDDEN_CHARACTERS = '/[\\\\:\x00]/';

    public static function isSafe(string $path): bool
    {
        if ($path === '' || strlen($path) > self::MAX_LENGTH || str_starts_with($path, self::SEPARATOR)) {
            return false;
        }

        if (preg_match(self::FORBIDDEN_CHARACTERS, $path) === 1) {
            return false;
        }

        foreach (explode(self::SEPARATOR, $path) as $segment) {
            if (in_array($segment, self::FORBIDDEN_SEGMENTS, true)) {
                return false;
            }
        }

        return true;
    }
}
