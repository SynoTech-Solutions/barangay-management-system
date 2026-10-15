<?php

namespace Tests\Unit\Domain;

use App\Domain\Residents\IdCardValidity;
use App\Domain\Residents\LengthOfStay;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class LengthOfStayTest extends TestCase
{
    public function test_length_of_stay_uses_calendar_years_and_months(): void
    {
        $stay = LengthOfStay::between(new DateTimeImmutable('2016-03-01'), new DateTimeImmutable('2026-02-28'));

        $this->assertNotNull($stay);
        $this->assertSame(9, $stay->years);
        $this->assertSame(11, $stay->months);

        $exact = LengthOfStay::between(new DateTimeImmutable('2016-03-01'), new DateTimeImmutable('2026-03-01'));
        $this->assertSame(10, $exact?->years);
        $this->assertSame(0, $exact?->months);
    }

    public function test_unknown_or_future_start_has_no_length(): void
    {
        $today = new DateTimeImmutable('2026-01-01');

        $this->assertNull(LengthOfStay::between(null, $today));
        $this->assertNull(LengthOfStay::between(new DateTimeImmutable('2026-05-01'), $today));
    }

    public function test_id_card_validity_adds_whole_months(): void
    {
        $this->assertSame('2027-03-15', IdCardValidity::until(new DateTimeImmutable('2026-03-15'), 12)->format('Y-m-d'));
        $this->assertSame('2026-09-15', IdCardValidity::until(new DateTimeImmutable('2026-03-15'), 6)->format('Y-m-d'));
    }
}
