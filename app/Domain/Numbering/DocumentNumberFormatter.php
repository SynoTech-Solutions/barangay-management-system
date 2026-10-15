<?php

namespace App\Domain\Numbering;

final class DocumentNumberFormatter
{
    private const string SEPARATOR = '-';

    private const string PAD_CHARACTER = '0';

    public static function format(NumberingRule $rule, int $period, int $sequence, int $width): string
    {
        $parts = [$rule->prefix];

        if ($rule->resetsYearly) {
            $parts[] = (string) $period;
        }

        $parts[] = str_pad((string) $sequence, $width, self::PAD_CHARACTER, STR_PAD_LEFT);

        return implode(self::SEPARATOR, $parts);
    }
}
