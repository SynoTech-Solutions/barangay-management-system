<?php

namespace App\Domain\Certificates;

use App\Enums\ClearanceValidityMode;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final class ClearanceExpiry
{
    public const int DEFAULT_MONTHS = 12;

    public static function until(DateTimeInterface $issuedOn, ClearanceValidityMode $mode, ?int $months): CarbonImmutable
    {
        $issued = CarbonImmutable::instance($issuedOn)->startOfDay();

        return match ($mode) {
            ClearanceValidityMode::EndOfYear => $issued->endOfYear()->startOfDay(),
            ClearanceValidityMode::Months => $issued->addMonthsNoOverflow($months ?? self::DEFAULT_MONTHS),
        };
    }

    public static function isExpired(DateTimeInterface $expiresOn, DateTimeInterface $today): bool
    {
        return CarbonImmutable::instance($expiresOn)->startOfDay()->lessThan(CarbonImmutable::instance($today)->startOfDay());
    }
}
