<?php

namespace App\Domain\Geography;

final readonly class PsgcCode
{
    public const int LENGTH = 10;

    private const int REGION_DIGITS = 2;

    private const int PROVINCE_DIGITS = 5;

    private const string FILL = '0';

    private function __construct(public string $value) {}

    public static function tryFrom(string $value): ?self
    {
        $digits = trim($value);
        if (preg_match('/^[0-9]+$/', $digits) !== 1) {
            return null;
        }

        $digits = str_pad($digits, self::LENGTH, self::FILL, STR_PAD_LEFT);

        return strlen($digits) === self::LENGTH ? new self($digits) : null;
    }

    public function regionCode(): string
    {
        return $this->truncate(self::REGION_DIGITS);
    }

    public function provinceCode(): string
    {
        return $this->truncate(self::PROVINCE_DIGITS);
    }

    private function truncate(int $digits): string
    {
        return str_pad(substr($this->value, 0, $digits), self::LENGTH, self::FILL);
    }
}
