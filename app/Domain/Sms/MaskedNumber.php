<?php

namespace App\Domain\Sms;

final class MaskedNumber
{
    public const int VISIBLE_DIGITS = 4;

    public const string MASK = '***';

    public static function of(?string $number): string
    {
        if ($number === null || $number === '') {
            return self::MASK;
        }

        $digits = (string) preg_replace('/\D/', '', $number);

        return self::MASK.substr($digits, -self::VISIBLE_DIGITS);
    }
}
