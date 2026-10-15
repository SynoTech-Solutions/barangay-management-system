<?php

namespace App\Domain\Finance;

use InvalidArgumentException;

final class AmountInWords
{
    private const int CENTAVOS_PER_PESO = 100;

    private const int GROUP_SIZE = 1000;

    private const int HUNDRED = 100;

    private const int TEN = 10;

    private const int TWENTY = 20;

    private const array UNITS = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];

    private const array TEENS = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const array TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    private const array SCALES = ['', 'Thousand', 'Million', 'Billion', 'Trillion'];

    private const string ONE = 'One';

    private const string PESO = 'Peso';

    private const string PESOS = 'Pesos';

    private const string CENT = 'Cent';

    private const string CENTS = 'Cents';

    private const string ZERO = 'Zero';

    private const string AND = 'And';

    private const string HUNDRED_WORD = 'Hundred';

    private const string ONLY = 'Only';

    private const string DECIMAL_PATTERN = '/^(\d{1,15})(?:\.(\d+))?$/';

    private const int CENT_DIGITS = 2;

    private const int ROUND_UP_FROM = 5;

    public static function fromDecimal(string $amount, bool $uppercase = false): string
    {
        if (preg_match(self::DECIMAL_PATTERN, trim($amount), $parts) !== 1) {
            throw new InvalidArgumentException("Not a non-negative decimal amount: {$amount}");
        }

        $fraction = str_pad($parts[2] ?? '', self::CENT_DIGITS + 1, '0');
        $centavos = (int) $parts[1] * self::CENTAVOS_PER_PESO + (int) substr($fraction, 0, self::CENT_DIGITS);
        if ((int) $fraction[self::CENT_DIGITS] >= self::ROUND_UP_FROM) {
            $centavos++;
        }

        return self::fromCentavos($centavos, $uppercase);
    }

    public static function fromCentavos(int $centavos, bool $uppercase = false): string
    {
        if ($centavos < 0) {
            throw new InvalidArgumentException('Amounts in words cannot be negative.');
        }

        $pesos = intdiv($centavos, self::CENTAVOS_PER_PESO);
        $cents = $centavos % self::CENTAVOS_PER_PESO;

        $parts = match (true) {
            $pesos === 0 && $cents === 0 => [self::ZERO, self::PESOS],
            $cents === 0 => [self::pesoPhrase($pesos)],
            $pesos === 0 => [self::centPhrase($cents)],
            default => [self::pesoPhrase($pesos), self::AND, self::centPhrase($cents)],
        };

        $words = implode(' ', [...$parts, self::ONLY]);

        return $uppercase ? mb_strtoupper($words) : $words;
    }

    private static function pesoPhrase(int $pesos): string
    {
        $words = self::integerWords($pesos);

        return $words.' '.($words === self::ONE ? self::PESO : self::PESOS);
    }

    private static function centPhrase(int $cents): string
    {
        $words = self::groupWords($cents);

        return $words.' '.($words === self::ONE ? self::CENT : self::CENTS);
    }

    private static function integerWords(int $value): string
    {
        $groups = [];
        $scale = 0;
        while ($value > 0) {
            if ($scale >= count(self::SCALES)) {
                throw new InvalidArgumentException('Amount is too large to write in words.');
            }

            $group = $value % self::GROUP_SIZE;
            if ($group !== 0) {
                array_unshift($groups, trim(self::groupWords($group).' '.self::SCALES[$scale]));
            }
            $value = intdiv($value, self::GROUP_SIZE);
            $scale++;
        }

        return implode(' ', $groups);
    }

    private static function groupWords(int $value): string
    {
        $hundreds = intdiv($value, self::HUNDRED);
        $rest = $value % self::HUNDRED;
        $words = [];

        if ($hundreds > 0) {
            $words[] = self::UNITS[$hundreds];
            $words[] = self::HUNDRED_WORD;
        }

        if ($rest >= self::TWENTY) {
            $words[] = self::TENS[intdiv($rest, self::TEN)];
            $words[] = self::UNITS[$rest % self::TEN];
        } elseif ($rest >= self::TEN) {
            $words[] = self::TEENS[$rest - self::TEN];
        } else {
            $words[] = self::UNITS[$rest];
        }

        return implode(' ', array_filter($words, fn (string $word): bool => $word !== ''));
    }
}
