<?php

namespace Tests\Unit\Domain;

use App\Domain\Geography\PsgcCode;
use PHPUnit\Framework\TestCase;

class PsgcCodeTest extends TestCase
{
    public function test_parent_codes_are_derived_from_the_prefix(): void
    {
        $code = PsgcCode::tryFrom('9902801001');

        $this->assertNotNull($code);
        $this->assertSame('9900000000', $code->regionCode());
        $this->assertSame('9902800000', $code->provinceCode());
    }

    public function test_short_numeric_codes_are_left_padded(): void
    {
        $this->assertSame('0100000000', PsgcCode::tryFrom('100000000')?->value);
    }

    public function test_invalid_codes_are_rejected(): void
    {
        $this->assertNull(PsgcCode::tryFrom('01-0000000'));
        $this->assertNull(PsgcCode::tryFrom('12345678901'));
        $this->assertNull(PsgcCode::tryFrom(''));
    }
}
