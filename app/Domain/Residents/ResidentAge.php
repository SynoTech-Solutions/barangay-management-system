<?php

namespace App\Domain\Residents;

use DateTimeInterface;

final class ResidentAge
{
    public const int SENIOR_CITIZEN_AGE = 60;

    public static function years(?DateTimeInterface $birthdate, DateTimeInterface $on): ?int
    {
        if ($birthdate === null || $birthdate > $on) {
            return null;
        }

        return $birthdate->diff($on)->y;
    }

    public static function isSenior(?DateTimeInterface $birthdate, DateTimeInterface $on): bool
    {
        $years = self::years($birthdate, $on);

        return $years !== null && $years >= self::SENIOR_CITIZEN_AGE;
    }
}
