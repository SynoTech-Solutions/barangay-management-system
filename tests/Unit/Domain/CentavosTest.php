<?php

namespace Tests\Unit\Domain;

use App\Domain\Money\Centavos;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CentavosTest extends TestCase
{
    public function test_decimal_strings_round_trip_through_centavos(): void
    {
        $this->assertSame(12345, Centavos::fromDecimal('123.45'));
        $this->assertSame(1250, Centavos::fromDecimal('12.5'));
        $this->assertSame(700, Centavos::fromDecimal('7'));
        $this->assertSame(-5, Centavos::fromDecimal('-0.05'));
        $this->assertSame('123.45', Centavos::toDecimal(12345));
        $this->assertSame('0.05', Centavos::toDecimal(5));
        $this->assertSame('-1.20', Centavos::toDecimal(-120));
    }

    public function test_sums_are_exact(): void
    {
        $this->assertSame('0.30', Centavos::sum('0.10', '0.20'));
        $this->assertSame('130.00', Centavos::sum('100.00', '30'));
    }

    public function test_more_than_two_decimal_places_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Centavos::fromDecimal('1.005');
    }

    public function test_percentages_round_half_up(): void
    {
        $this->assertSame(1, Centavos::percentOf(25, 200));
        $this->assertSame(0, Centavos::percentOf(24, 200));
        $this->assertSame(2_400, Centavos::percentOf(10_000, 2_400));
    }
}
