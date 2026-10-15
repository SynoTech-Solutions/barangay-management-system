<?php

namespace Tests\Unit\Domain;

use App\Domain\Sms\SmsSegments;
use App\Enums\SmsEncoding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SmsSegmentsTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: SmsEncoding, 2: int, 3: int}>
     */
    public static function boundaries(): array
    {
        return [
            'empty' => ['', SmsEncoding::Gsm7, 0, 0],
            'gsm one character' => ['a', SmsEncoding::Gsm7, 1, 1],
            'gsm 160 fits one segment' => [str_repeat('a', 160), SmsEncoding::Gsm7, 160, 1],
            'gsm 161 needs two' => [str_repeat('a', 161), SmsEncoding::Gsm7, 161, 2],
            'gsm 306 is two of 153' => [str_repeat('a', 306), SmsEncoding::Gsm7, 306, 2],
            'gsm 307 needs three' => [str_repeat('a', 307), SmsEncoding::Gsm7, 307, 3],
            'gsm extension counts double' => [str_repeat('€', 80), SmsEncoding::Gsm7, 160, 1],
            'gsm extension over the single limit' => [str_repeat('€', 81), SmsEncoding::Gsm7, 162, 2],
            'gsm extension never splits across segments' => [str_repeat('a', 152).'€'.str_repeat('a', 10), SmsEncoding::Gsm7, 164, 2],
            'escape split pushes to a third segment' => [str_repeat('a', 152).'['.str_repeat('a', 152), SmsEncoding::Gsm7, 306, 3],
            'ucs2 70 fits one segment' => [str_repeat('ñ', 69).'ő', SmsEncoding::Ucs2, 70, 1],
            'ucs2 71 needs two' => [str_repeat('ő', 71), SmsEncoding::Ucs2, 71, 2],
            'ucs2 134 is two of 67' => [str_repeat('ő', 134), SmsEncoding::Ucs2, 134, 2],
            'ucs2 135 needs three' => [str_repeat('ő', 135), SmsEncoding::Ucs2, 135, 3],
            'emoji uses two code units' => [str_repeat('😀', 35), SmsEncoding::Ucs2, 70, 1],
            'emoji never splits a surrogate pair' => [str_repeat('a', 66).'😀'.str_repeat('a', 10), SmsEncoding::Ucs2, 78, 2],
            'filipino text stays gsm' => ['Magandang umaga po! Pulong sa Sabado, 8:00 AM.', SmsEncoding::Gsm7, 46, 1],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_segments_follow_gsm_and_ucs2_limits(string $body, SmsEncoding $encoding, int $units, int $segments): void
    {
        $length = SmsSegments::of($body);

        $this->assertSame($encoding, $length->encoding);
        $this->assertSame($units, $length->units);
        $this->assertSame($segments, $length->segments);
    }

    public function test_the_maximum_segment_count_is_enforced_by_exceeds(): void
    {
        $limit = SmsSegments::GSM_MULTI * SmsSegments::MAX_SEGMENTS;

        $this->assertFalse(SmsSegments::of(str_repeat('a', $limit))->exceeds(SmsSegments::MAX_SEGMENTS));
        $this->assertTrue(SmsSegments::of(str_repeat('a', $limit + 1))->exceeds(SmsSegments::MAX_SEGMENTS));
    }
}
