<?php

namespace App\Support;

final class CacheKeys
{
    private const string PREFIX = 'bms';

    private const string GLOBAL_SCOPE = 'global';

    public static function featureOverrides(?int $barangayId): string
    {
        return implode(':', [self::PREFIX, 'feature-overrides', $barangayId ?? self::GLOBAL_SCOPE]);
    }

    public static function readinessProbe(): string
    {
        return implode(':', [self::PREFIX, 'readiness-probe']);
    }
}
