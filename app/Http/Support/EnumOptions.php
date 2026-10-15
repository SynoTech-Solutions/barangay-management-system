<?php

namespace App\Http\Support;

use BackedEnum;

final class EnumOptions
{
    /**
     * @param  array<int, BackedEnum>  $cases
     * @return list<array{value: string|int, label: string}>
     */
    public static function of(array $cases): array
    {
        return array_values(array_map(
            fn (BackedEnum $case): array => ['value' => $case->value, 'label' => method_exists($case, 'labelKey') ? (string) __($case->labelKey()) : (string) $case->value],
            $cases,
        ));
    }
}
