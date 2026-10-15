<?php

namespace Tests\Unit\ImportBundle;

use App\Domain\ImportBundle\SourceNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SourceNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: int|null, 2: int|null}>
     */
    public static function numbers(): array
    {
        return [
            'system format' => ['BCC-2024-000007', 2024, 7],
            'year first' => ['2024-0007', 2024, 7],
            'year month counter' => ['2024-03-005', 2024, 5],
            'no year' => ['RES-000010', null, 10],
            'plain digits' => ['42', null, 42],
            'unit prefix is not a year' => ['9900001-0010', null, 10],
            'out of range year' => ['1234-0005', null, 5],
            'no trailing digits' => ['ABC', null, null],
            'blank' => ['  ', null, null],
            'null' => [null, null, null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_it_reads_the_year_and_trailing_counter(?string $number, ?int $year, ?int $counter): void
    {
        $parsed = SourceNumber::parse($number);

        $this->assertSame($counter, $parsed?->counter);
        $this->assertSame($year, $parsed?->year);
    }
}
