<?php

namespace App\Domain\Sms;

use App\Enums\SmsEncoding;

final class SmsSegments
{
    public const int MAX_SEGMENTS = 5;

    public const int GSM_SINGLE = 160;

    public const int GSM_MULTI = 153;

    public const int UCS2_SINGLE = 70;

    public const int UCS2_MULTI = 67;

    private const string GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    private const string GSM_EXTENDED = "^{}\\[~]|€\f";

    private const int BASIC_UNITS = 1;

    private const int EXTENDED_UNITS = 2;

    private const int BMP_MAX = 0xFFFF;

    public static function of(string $body): SmsLength
    {
        $characters = mb_str_split($body);
        $gsmUnits = self::gsmUnits($characters);

        if ($gsmUnits !== null) {
            return new SmsLength(SmsEncoding::Gsm7, array_sum($gsmUnits), self::count($gsmUnits, self::GSM_SINGLE, self::GSM_MULTI));
        }

        $ucsUnits = array_map(fn (string $character): int => mb_ord($character) > self::BMP_MAX ? 2 : 1, $characters);

        return new SmsLength(SmsEncoding::Ucs2, array_sum($ucsUnits), self::count($ucsUnits, self::UCS2_SINGLE, self::UCS2_MULTI));
    }

    /**
     * @param  list<string>  $characters
     * @return list<int>|null
     */
    private static function gsmUnits(array $characters): ?array
    {
        static $basic = null, $extended = null;
        $basic ??= array_flip(mb_str_split(self::GSM_BASIC));
        $extended ??= array_flip(mb_str_split(self::GSM_EXTENDED));

        $units = [];
        foreach ($characters as $character) {
            if (isset($basic[$character])) {
                $units[] = self::BASIC_UNITS;
            } elseif (isset($extended[$character])) {
                $units[] = self::EXTENDED_UNITS;
            } else {
                return null;
            }
        }

        return $units;
    }

    /**
     * @param  list<int>  $units
     */
    private static function count(array $units, int $single, int $multi): int
    {
        $total = array_sum($units);
        if ($total === 0) {
            return 0;
        }
        if ($total <= $single) {
            return 1;
        }

        $segments = 1;
        $used = 0;
        foreach ($units as $unit) {
            if ($used + $unit > $multi) {
                $segments++;
                $used = 0;
            }
            $used += $unit;
        }

        return $segments;
    }
}
