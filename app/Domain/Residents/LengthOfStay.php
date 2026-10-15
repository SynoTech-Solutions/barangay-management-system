<?php

namespace App\Domain\Residents;

use DateTimeInterface;

final readonly class LengthOfStay
{
    private function __construct(
        public int $years,
        public int $months,
    ) {}

    public static function between(?DateTimeInterface $since, DateTimeInterface $on): ?self
    {
        if ($since === null || $since > $on) {
            return null;
        }

        $interval = $since->diff($on);

        return new self($interval->y, $interval->m);
    }
}
