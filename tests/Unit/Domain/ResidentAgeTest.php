<?php

namespace Tests\Unit\Domain;

use App\Domain\Residents\ResidentAge;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ResidentAgeTest extends TestCase
{
    public function test_age_counts_completed_years_on_the_reference_date(): void
    {
        $birthdate = new DateTimeImmutable('1966-03-15');

        $this->assertSame(59, ResidentAge::years($birthdate, new DateTimeImmutable('2026-03-14')));
        $this->assertSame(60, ResidentAge::years($birthdate, new DateTimeImmutable('2026-03-15')));
    }

    public function test_senior_status_starts_at_sixty(): void
    {
        $birthdate = new DateTimeImmutable('1966-03-15');

        $this->assertFalse(ResidentAge::isSenior($birthdate, new DateTimeImmutable('2026-03-14')));
        $this->assertTrue(ResidentAge::isSenior($birthdate, new DateTimeImmutable('2026-03-15')));
    }

    public function test_unknown_or_future_birthdates_have_no_age(): void
    {
        $today = new DateTimeImmutable('2026-01-01');

        $this->assertNull(ResidentAge::years(null, $today));
        $this->assertNull(ResidentAge::years(new DateTimeImmutable('2026-06-01'), $today));
        $this->assertFalse(ResidentAge::isSenior(null, $today));
    }
}
