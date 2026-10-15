<?php

namespace App\Domain\Forms;

use InvalidArgumentException;

final class Ordinal
{
    private const array SUFFIXES = [1 => 'st', 2 => 'nd', 3 => 'rd'];

    private const string DEFAULT_SUFFIX = 'th';

    private const array TEENS = [11, 12, 13];

    public static function of(int $number): string
    {
        if ($number < 0) {
            throw new InvalidArgumentException('Ordinals are defined for non-negative numbers only.');
        }

        $suffix = in_array($number % 100, self::TEENS, true) ? self::DEFAULT_SUFFIX : (self::SUFFIXES[$number % 10] ?? self::DEFAULT_SUFFIX);

        return $number.$suffix;
    }
}
