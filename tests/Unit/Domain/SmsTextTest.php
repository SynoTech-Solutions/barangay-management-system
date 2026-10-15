<?php

namespace Tests\Unit\Domain;

use App\Domain\Sms\MaskedNumber;
use App\Domain\Sms\SmsRetryPolicy;
use App\Domain\Sms\SmsText;
use PHPUnit\Framework\TestCase;

class SmsTextTest extends TestCase
{
    public function test_control_characters_are_removed_and_line_breaks_kept(): void
    {
        $this->assertSame("Line one\nLine two", SmsText::clean("  Line\u{0007} one\r\nLine\u{200B} two\u{202E}\t "));
        $this->assertSame('', SmsText::clean("\u{0000}\u{FEFF} "));
    }

    public function test_numbers_are_masked_to_the_last_four_digits(): void
    {
        $this->assertSame('***0001', MaskedNumber::of('+639170000001'));
        $this->assertSame('***', MaskedNumber::of(null));
    }

    public function test_retry_delay_grows_and_is_capped(): void
    {
        $this->assertSame(60, SmsRetryPolicy::delaySeconds(1, 60, 3600));
        $this->assertSame(120, SmsRetryPolicy::delaySeconds(2, 60, 3600));
        $this->assertSame(3600, SmsRetryPolicy::delaySeconds(10, 60, 3600));
        $this->assertTrue(SmsRetryPolicy::canRetry(4, 5));
        $this->assertFalse(SmsRetryPolicy::canRetry(5, 5));
    }
}
