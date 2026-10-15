<?php

namespace App\Domain\ImportBundle;

final readonly class SourceNumber
{
    private const string SEGMENTS = '/[^A-Za-z0-9]+/';

    private const string TRAILING_DIGITS = '/(\d{1,9})$/';

    private const string YEAR = '/^\d{4}$/';

    private const int MIN_YEAR = 1900;

    private const int MAX_YEAR = 2999;

    public function __construct(
        public ?int $year,
        public int $counter,
    ) {}

    public static function parse(?string $number): ?self
    {
        if ($number === null || trim($number) === '') {
            return null;
        }

        $segments = array_values(array_filter(preg_split(self::SEGMENTS, trim($number)) ?: [], fn (string $segment): bool => $segment !== ''));
        if ($segments === [] || preg_match(self::TRAILING_DIGITS, $segments[count($segments) - 1], $m) !== 1) {
            return null;
        }

        $year = null;
        foreach (array_slice($segments, 0, -1) as $segment) {
            if (preg_match(self::YEAR, $segment) === 1 && (int) $segment >= self::MIN_YEAR && (int) $segment <= self::MAX_YEAR) {
                $year = (int) $segment;

                break;
            }
        }

        return new self($year, (int) $m[1]);
    }
}
