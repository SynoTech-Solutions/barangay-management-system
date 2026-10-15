<?php

namespace Tests\Unit\Domain;

use App\Domain\Residents\AgeBands;
use PHPUnit\Framework\TestCase;

class AgeBandsTest extends TestCase
{
    public function test_ages_fall_into_inclusive_bands(): void
    {
        $this->assertSame('0-4', AgeBands::bandFor(0));
        $this->assertSame('0-4', AgeBands::bandFor(4));
        $this->assertSame('15-17', AgeBands::bandFor(17));
        $this->assertSame('18-24', AgeBands::bandFor(18));
        $this->assertSame('60-79', AgeBands::bandFor(60));
        $this->assertSame('80+', AgeBands::bandFor(104));
        $this->assertSame(AgeBands::UNKNOWN, AgeBands::bandFor(null));
    }

    public function test_fold_sums_counts_per_band_and_keeps_every_band(): void
    {
        $bands = AgeBands::fold([3 => 2, 4 => 1, 70 => 5, 'unknown' => 4]);

        $this->assertSame(3, $bands['0-4']);
        $this->assertSame(5, $bands['60-79']);
        $this->assertSame(4, $bands[AgeBands::UNKNOWN]);
        $this->assertSame(0, $bands['80+']);
        $this->assertCount(count(AgeBands::LOWER_BOUNDS) + 1, $bands);
    }
}
