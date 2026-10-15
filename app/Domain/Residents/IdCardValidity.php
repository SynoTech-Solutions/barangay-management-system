<?php

namespace App\Domain\Residents;

use DateTimeImmutable;
use DateTimeInterface;

final class IdCardValidity
{
    public static function until(DateTimeInterface $issuedOn, int $months): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($issuedOn)->modify("+{$months} months");
    }
}
