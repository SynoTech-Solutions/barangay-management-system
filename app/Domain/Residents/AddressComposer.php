<?php

namespace App\Domain\Residents;

final class AddressComposer
{
    private const string SEPARATOR = ', ';

    /**
     * @param  list<string|null>  $parts
     */
    public static function compose(array $parts): string
    {
        return implode(self::SEPARATOR, array_values(array_filter(
            array_map(fn (?string $part): ?string => NameNormalizer::normalize($part), $parts),
            fn (?string $part): bool => $part !== null,
        )));
    }
}
