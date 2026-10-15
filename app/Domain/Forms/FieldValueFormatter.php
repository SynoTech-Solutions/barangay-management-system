<?php

namespace App\Domain\Forms;

use App\Domain\Money\Centavos;
use App\Enums\FormInputType;
use DateTimeImmutable;

final class FieldValueFormatter
{
    public const string DATE_INPUT_FORMAT = 'Y-m-d';

    public const string DATE_DISPLAY_FORMAT = 'F j, Y';

    public static function display(FormInputType $type, string|int|bool|null $value, CheckLabels $labels): string
    {
        if ($value === null || $value === '') {
            return $type === FormInputType::Check ? $labels->unchecked : '';
        }

        return match ($type) {
            FormInputType::Check => $value === true ? $labels->checked : $labels->unchecked,
            FormInputType::Date => self::date((string) $value),
            FormInputType::Amount => Centavos::grouped(Centavos::fromDecimal((string) $value)),
            FormInputType::Text, FormInputType::Resident, FormInputType::Table => (string) $value,
        };
    }

    public static function date(string $isoDate): string
    {
        $date = DateTimeImmutable::createFromFormat('!'.self::DATE_INPUT_FORMAT, $isoDate);

        return $date === false ? $isoDate : $date->format(self::DATE_DISPLAY_FORMAT);
    }
}
