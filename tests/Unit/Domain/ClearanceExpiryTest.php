<?php

namespace Tests\Unit\Domain;

use App\Domain\Certificates\ClearanceExpiry;
use App\Enums\ClearanceValidityMode;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ClearanceExpiryTest extends TestCase
{
    public function test_end_of_year_mode_expires_on_december_31_of_the_issue_year(): void
    {
        $expiry = ClearanceExpiry::until(new CarbonImmutable('2026-03-15 16:30:00'), ClearanceValidityMode::EndOfYear, 6);

        $this->assertSame('2026-12-31', $expiry->toDateString());
    }

    public function test_months_mode_adds_months_without_overflowing_short_months(): void
    {
        $this->assertSame('2026-09-15', ClearanceExpiry::until(new CarbonImmutable('2026-03-15'), ClearanceValidityMode::Months, 6)->toDateString());
        $this->assertSame('2027-02-28', ClearanceExpiry::until(new CarbonImmutable('2026-08-31'), ClearanceValidityMode::Months, 6)->toDateString());
    }

    public function test_months_mode_without_a_count_uses_the_default(): void
    {
        $expiry = ClearanceExpiry::until(new CarbonImmutable('2026-01-10'), ClearanceValidityMode::Months, null);

        $this->assertSame('2027-01-10', $expiry->toDateString());
    }

    public function test_a_clearance_is_valid_through_its_expiry_date(): void
    {
        $expiresOn = new CarbonImmutable('2026-12-31');

        $this->assertFalse(ClearanceExpiry::isExpired($expiresOn, new CarbonImmutable('2026-12-31 23:00:00')));
        $this->assertTrue(ClearanceExpiry::isExpired($expiresOn, new CarbonImmutable('2027-01-01')));
    }
}
