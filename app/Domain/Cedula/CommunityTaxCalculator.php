<?php

namespace App\Domain\Cedula;

use App\Domain\Money\Centavos;
use DateTimeInterface;

final class CommunityTaxCalculator
{
    public const int BASIC_TAX = 500;

    public const int EXEMPT_BASIC_TAX = 100;

    public const int INCOME_BRACKET = 100_000;

    public const int TAX_PER_BRACKET = 100;

    public const int ADDITIONAL_TAX_CAP = 500_000;

    public const int DUE_MONTH = 2;

    public const int INTEREST_BASIS_POINTS_PER_MONTH = 200;

    public const int MAX_INTEREST_MONTHS = 12;

    private const int MONTHS_PER_YEAR = 12;

    public static function individual(IndividualTaxInput $input, DateTimeInterface $paidOn, bool $withInterest): CommunityTaxBreakdown
    {
        $basic = $input->exempt ? self::EXEMPT_BASIC_TAX : self::BASIC_TAX;
        $business = self::bracketTax($input->businessGrossReceipts);
        $salary = self::bracketTax($input->salaries);
        $realProperty = self::bracketTax($input->realPropertyIncome);

        $uncapped = $business + $salary + $realProperty;
        $additional = min($uncapped, self::ADDITIONAL_TAX_CAP);
        $subtotal = $basic + $additional;

        $months = $withInterest ? self::interestMonths($input->taxYear, $paidOn) : 0;
        $basisPoints = $months * self::INTEREST_BASIS_POINTS_PER_MONTH;
        $interest = Centavos::percentOf($subtotal, $basisPoints);

        return new CommunityTaxBreakdown(
            input: $input,
            basic: $basic,
            business: $business,
            salary: $salary,
            realProperty: $realProperty,
            additional: $additional,
            additionalCapped: $uncapped > self::ADDITIONAL_TAX_CAP,
            subtotal: $subtotal,
            interestApplied: $withInterest,
            interestMonths: $months,
            interestBasisPoints: $basisPoints,
            interest: $interest,
            total: $subtotal + $interest,
        );
    }

    public static function bracketTax(int $income): int
    {
        return intdiv($income, self::INCOME_BRACKET) * self::TAX_PER_BRACKET;
    }

    public static function interestMonths(int $taxYear, DateTimeInterface $paidOn): int
    {
        $paidYear = (int) $paidOn->format('Y');
        $paidMonth = (int) $paidOn->format('n');
        $monthsLate = ($paidYear - $taxYear) * self::MONTHS_PER_YEAR + $paidMonth - self::DUE_MONTH;

        return max(0, min($monthsLate, self::MAX_INTEREST_MONTHS));
    }
}
