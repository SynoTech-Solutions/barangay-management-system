<?php

namespace Tests\Unit\Domain;

use App\Domain\Finance\AmountInWords;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function goldens(): array
    {
        return [
            '0' => [0, 'Zero Pesos Only'],
            '0.01' => [1, 'One Cent Only'],
            '0.50' => [50, 'Fifty Cents Only'],
            '1' => [100, 'One Peso Only'],
            '1.01' => [101, 'One Peso And One Cent Only'],
            '11' => [1_100, 'Eleven Pesos Only'],
            '12' => [1_200, 'Twelve Pesos Only'],
            '13' => [1_300, 'Thirteen Pesos Only'],
            '14' => [1_400, 'Fourteen Pesos Only'],
            '15' => [1_500, 'Fifteen Pesos Only'],
            '16' => [1_600, 'Sixteen Pesos Only'],
            '17' => [1_700, 'Seventeen Pesos Only'],
            '18' => [1_800, 'Eighteen Pesos Only'],
            '19' => [1_900, 'Nineteen Pesos Only'],
            '20' => [2_000, 'Twenty Pesos Only'],
            '21' => [2_100, 'Twenty One Pesos Only'],
            '100' => [10_000, 'One Hundred Pesos Only'],
            '101' => [10_100, 'One Hundred One Pesos Only'],
            '110' => [11_000, 'One Hundred Ten Pesos Only'],
            '1,000' => [100_000, 'One Thousand Pesos Only'],
            '1,001' => [100_100, 'One Thousand One Pesos Only'],
            '1,234.56' => [123_456, 'One Thousand Two Hundred Thirty Four Pesos And Fifty Six Cents Only'],
            '10,000' => [1_000_000, 'Ten Thousand Pesos Only'],
            '20.20' => [2_020, 'Twenty Pesos And Twenty Cents Only'],
            '10.99' => [1_099, 'Ten Pesos And Ninety Nine Cents Only'],
            '100,000' => [10_000_000, 'One Hundred Thousand Pesos Only'],
            '1,000,000' => [100_000_000, 'One Million Pesos Only'],
            '1,000,001' => [100_000_100, 'One Million One Pesos Only'],
            '999,999,999.99' => [99_999_999_999, 'Nine Hundred Ninety Nine Million Nine Hundred Ninety Nine Thousand Nine Hundred Ninety Nine Pesos And Ninety Nine Cents Only'],
            '1,000,000,000' => [100_000_000_000, 'One Billion Pesos Only'],
            '9,999,999,999.99' => [999_999_999_999, 'Nine Billion Nine Hundred Ninety Nine Million Nine Hundred Ninety Nine Thousand Nine Hundred Ninety Nine Pesos And Ninety Nine Cents Only'],
        ];
    }

    #[DataProvider('goldens')]
    public function test_amounts_are_written_in_title_case_with_single_spaces(int $centavos, string $expected): void
    {
        $this->assertSame($expected, AmountInWords::fromCentavos($centavos));
    }

    public function test_uppercase_is_a_presentation_option(): void
    {
        $this->assertSame('ONE THOUSAND TWO HUNDRED THIRTY FOUR PESOS AND FIFTY SIX CENTS ONLY', AmountInWords::fromCentavos(123_456, uppercase: true));
    }

    public function test_negative_amounts_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AmountInWords::fromCentavos(-1);
    }

    public function test_amounts_beyond_the_trillions_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AmountInWords::fromCentavos(PHP_INT_MAX);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function decimals(): array
    {
        return [
            'spec 1' => ['1', 'One Peso Only'],
            'spec 100' => ['100', 'One Hundred Pesos Only'],
            'spec 110' => ['110', 'One Hundred Ten Pesos Only'],
            'spec 1234.56' => ['1234.56', 'One Thousand Two Hundred Thirty Four Pesos And Fifty Six Cents Only'],
            'spec 1000 single space' => ['1000', 'One Thousand Pesos Only'],
            'spec 20.20 single space' => ['20.20', 'Twenty Pesos And Twenty Cents Only'],
            'spec 0.01 no leading and' => ['0.01', 'One Cent Only'],
            'spec billion spacing' => ['1000000000', 'One Billion Pesos Only'],
            'spec zero' => ['0', 'Zero Pesos Only'],
            'spec 10.999 rounds half up' => ['10.999', 'Eleven Pesos Only'],
            'half up from five' => ['0.005', 'One Cent Only'],
            'below half stays' => ['0.004', 'Zero Pesos Only'],
            'one decimal place' => ['0.5', 'Fifty Cents Only'],
            'leading zero cents' => ['2.05', 'Two Pesos And Five Cents Only'],
            'trillions' => ['1000000000000', 'One Trillion Pesos Only'],
        ];
    }

    #[DataProvider('decimals')]
    public function test_decimal_strings_round_half_up_to_centavos_first(string $amount, string $expected): void
    {
        $this->assertSame($expected, AmountInWords::fromDecimal($amount));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidDecimals(): array
    {
        return [
            'negative' => ['-1'],
            'infinite' => ['INF'],
            'not a number' => ['NAN'],
            'exponent' => ['1e5'],
            'grouping' => ['1,000'],
            'empty' => [''],
            'too many digits' => ['1000000000000000'],
        ];
    }

    #[DataProvider('invalidDecimals')]
    public function test_negative_and_non_finite_amounts_are_rejected(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        AmountInWords::fromDecimal($amount);
    }
}
