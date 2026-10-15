<?php

namespace App\Domain\Money;

use InvalidArgumentException;

final class Centavos
{
    private const int PER_PESO = 100;

    private const int SCALE = 2;

    private const int BASIS_POINTS_WHOLE = 10_000;

    private const string PATTERN = '/^(-?)(\d+)(?:\.(\d{1,2}))?$/';

    public static function fromDecimal(string $amount): int
    {
        if (preg_match(self::PATTERN, trim($amount), $parts) !== 1) {
            throw new InvalidArgumentException("Not a decimal amount with at most two places: {$amount}");
        }

        $value = (int) $parts[2] * self::PER_PESO + (int) str_pad($parts[3] ?? '', self::SCALE, '0');

        return $parts[1] === '-' ? -$value : $value;
    }

    public static function toDecimal(int $centavos): string
    {
        $sign = $centavos < 0 ? '-' : '';
        $absolute = abs($centavos);

        return $sign.intdiv($absolute, self::PER_PESO).'.'.str_pad((string) ($absolute % self::PER_PESO), self::SCALE, '0', STR_PAD_LEFT);
    }

    public static function grouped(int $centavos): string
    {
        $sign = $centavos < 0 ? '-' : '';
        $absolute = abs($centavos);

        return $sign.number_format(intdiv($absolute, self::PER_PESO)).'.'.str_pad((string) ($absolute % self::PER_PESO), self::SCALE, '0', STR_PAD_LEFT);
    }

    public static function sum(string ...$amounts): string
    {
        return self::toDecimal(array_sum(array_map(self::fromDecimal(...), $amounts)));
    }

    public static function percentOf(int $centavos, int $basisPoints): int
    {
        if ($centavos < 0 || $basisPoints < 0) {
            throw new InvalidArgumentException('Percentages apply to non-negative amounts only.');
        }

        return intdiv($centavos * $basisPoints + intdiv(self::BASIS_POINTS_WHOLE, 2), self::BASIS_POINTS_WHOLE);
    }
}
