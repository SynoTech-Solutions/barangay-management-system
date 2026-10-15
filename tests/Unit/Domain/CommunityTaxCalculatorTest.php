<?php

namespace Tests\Unit\Domain;

use App\Domain\Cedula\CommunityTaxCalculator;
use App\Domain\Cedula\IndividualTaxInput;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CommunityTaxCalculatorTest extends TestCase
{
    private function input(int $business = 0, int $salaries = 0, int $realProperty = 0, bool $exempt = false, int $year = 2026): IndividualTaxInput
    {
        return new IndividualTaxInput($year, $exempt, $business, $salaries, $realProperty);
    }

    private function onTime(): CarbonImmutable
    {
        return new CarbonImmutable('2026-01-15');
    }

    public function test_basic_tax_is_five_pesos_or_one_peso_for_exempt_voluntary_payers(): void
    {
        $this->assertSame(500, CommunityTaxCalculator::individual($this->input(), $this->onTime(), false)->total);
        $this->assertSame(100, CommunityTaxCalculator::individual($this->input(exempt: true), $this->onTime(), false)->total);
    }

    public function test_each_component_is_one_peso_per_full_thousand(): void
    {
        $breakdown = CommunityTaxCalculator::individual($this->input(business: 100_000, salaries: 19_999_999, realProperty: 99_999), $this->onTime(), false);

        $this->assertSame(100, $breakdown->business);
        $this->assertSame(19_900, $breakdown->salary);
        $this->assertSame(0, $breakdown->realProperty);
        $this->assertSame(20_000, $breakdown->additional);
        $this->assertSame(20_500, $breakdown->total);
        $this->assertSame('205.00', $breakdown->toArray()['total']);
    }

    public function test_exact_multiples_and_one_centavo_below(): void
    {
        $this->assertSame(1_200, CommunityTaxCalculator::bracketTax(1_200_000));
        $this->assertSame(1_100, CommunityTaxCalculator::bracketTax(1_199_999));
        $this->assertSame(0, CommunityTaxCalculator::bracketTax(0));
    }

    public function test_the_additional_tax_is_capped_at_five_thousand_pesos(): void
    {
        $atCap = CommunityTaxCalculator::individual($this->input(salaries: 500_000_000), $this->onTime(), false);
        $overCap = CommunityTaxCalculator::individual($this->input(business: 300_000_000, salaries: 300_000_000), $this->onTime(), false);

        $this->assertSame(500_000, $atCap->additional);
        $this->assertFalse($atCap->additionalCapped);
        $this->assertSame(500_000, $overCap->additional);
        $this->assertTrue($overCap->additionalCapped);
        $this->assertSame(500_500, $overCap->total);
    }

    public function test_additional_is_the_sum_of_components_and_never_counted_twice(): void
    {
        $breakdown = CommunityTaxCalculator::individual($this->input(business: 1_000_000, salaries: 2_000_000, realProperty: 3_000_000), $this->onTime(), false);

        $this->assertSame($breakdown->business + $breakdown->salary + $breakdown->realProperty, $breakdown->additional);
        $this->assertSame($breakdown->basic + $breakdown->additional, $breakdown->subtotal);
        $this->assertSame(6_500, $breakdown->total);
    }

    public function test_no_interest_until_the_last_day_of_february(): void
    {
        $this->assertSame(0, CommunityTaxCalculator::interestMonths(2026, new CarbonImmutable('2026-02-28')));
        $this->assertSame(0, CommunityTaxCalculator::interestMonths(2028, new CarbonImmutable('2028-02-29')));
        $this->assertSame(0, CommunityTaxCalculator::interestMonths(2026, new CarbonImmutable('2025-12-20')));
        $this->assertSame(1, CommunityTaxCalculator::interestMonths(2026, new CarbonImmutable('2026-03-01')));
        $this->assertSame(1, CommunityTaxCalculator::interestMonths(2028, new CarbonImmutable('2028-03-01')));
        $this->assertSame(1, CommunityTaxCalculator::interestMonths(2026, new CarbonImmutable('2026-03-31')));
    }

    public function test_interest_is_two_percent_per_month_capped_at_twenty_four_percent(): void
    {
        $december = CommunityTaxCalculator::individual($this->input(salaries: 10_000_000), new CarbonImmutable('2026-12-15'), true);
        $this->assertSame(10, $december->interestMonths);
        $this->assertSame(2_000, $december->interestBasisPoints);
        $this->assertSame(10_500, $december->subtotal);
        $this->assertSame(2_100, $december->interest);
        $this->assertSame(12_600, $december->total);

        $late = CommunityTaxCalculator::individual($this->input(), new CarbonImmutable('2027-06-01'), true);
        $this->assertSame(12, $late->interestMonths);
        $this->assertSame(120, $late->interest);
        $this->assertSame(620, $late->total);
    }

    public function test_interest_on_whole_peso_subtotals_is_exact(): void
    {
        $march = CommunityTaxCalculator::individual($this->input(exempt: true, business: 2_500_000), new CarbonImmutable('2026-03-10'), true);
        $this->assertSame(2_600, $march->subtotal);
        $this->assertSame(52, $march->interest);

        $leapYearMay = CommunityTaxCalculator::individual($this->input(exempt: true, business: 100_000, year: 2028), new CarbonImmutable('2028-05-10'), true);
        $this->assertSame(200, $leapYearMay->subtotal);
        $this->assertSame(3, $leapYearMay->interestMonths);
        $this->assertSame(12, $leapYearMay->interest);
    }

    public function test_interest_is_not_charged_when_the_option_is_off(): void
    {
        $breakdown = CommunityTaxCalculator::individual($this->input(), new CarbonImmutable('2026-11-01'), false);

        $this->assertFalse($breakdown->interestApplied);
        $this->assertSame(0, $breakdown->interestMonths);
        $this->assertSame(0, $breakdown->interest);
        $this->assertSame(1, $breakdown->toArray()['v']);
    }
}
