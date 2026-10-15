<?php

namespace App\Domain\Residents;

final class AgeBands
{
    public const array LOWER_BOUNDS = [0, 5, 10, 15, 18, 25, 35, 45, 60, 80];

    public const string UNKNOWN = 'unknown';

    private const string RANGE_SEPARATOR = '-';

    private const string OPEN_END = '+';

    /**
     * @return list<string>
     */
    public static function labels(): array
    {
        return array_map(fn (int $index): string => self::labelAt($index), array_keys(self::LOWER_BOUNDS));
    }

    public static function bandFor(?int $age): string
    {
        if ($age === null || $age < 0) {
            return self::UNKNOWN;
        }

        $index = 0;
        foreach (self::LOWER_BOUNDS as $position => $lower) {
            if ($age >= $lower) {
                $index = $position;
            }
        }

        return self::labelAt($index);
    }

    /**
     * @param  array<int|string, int>  $countsByAge
     * @return array<string, int>
     */
    public static function fold(array $countsByAge): array
    {
        $bands = array_fill_keys([...self::labels(), self::UNKNOWN], 0);

        foreach ($countsByAge as $age => $count) {
            $bands[self::bandFor(is_int($age) ? $age : null)] += $count;
        }

        return $bands;
    }

    private static function labelAt(int $index): string
    {
        $lower = self::LOWER_BOUNDS[$index];
        $next = self::LOWER_BOUNDS[$index + 1] ?? null;

        return $next === null ? $lower.self::OPEN_END : $lower.self::RANGE_SEPARATOR.($next - 1);
    }
}
