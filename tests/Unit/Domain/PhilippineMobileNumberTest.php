<?php

namespace Tests\Unit\Domain;

use App\Domain\Residents\PhilippineMobileNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhilippineMobileNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function acceptedFormats(): array
    {
        return [
            'national' => ['09171234567'],
            'spaced' => ['0917 123 4567'],
            'masked' => ['(0917) 123-4567'],
            'international' => ['+639171234567'],
            'international without plus' => ['639171234567'],
            'subscriber only' => ['9171234567'],
        ];
    }

    #[DataProvider('acceptedFormats')]
    public function test_accepted_formats_normalize_to_the_national_form(string $input): void
    {
        $this->assertSame('09171234567', PhilippineMobileNumber::normalize($input));
    }

    public function test_landlines_and_short_numbers_are_rejected(): void
    {
        $this->assertNull(PhilippineMobileNumber::normalize('(02) 8123 4567'));
        $this->assertNull(PhilippineMobileNumber::normalize('0917123456'));
        $this->assertNull(PhilippineMobileNumber::normalize('08171234567'));
        $this->assertFalse(PhilippineMobileNumber::isValid('not a number'));
    }

    #[DataProvider('acceptedFormats')]
    public function test_accepted_formats_convert_to_e164(string $input): void
    {
        $this->assertSame('+639171234567', PhilippineMobileNumber::toE164($input));
    }

    public function test_e164_rejects_what_normalization_rejects(): void
    {
        foreach (['', '(02) 8123 4567', '0917123456', '091712345678', '08171234567', '+1 917 123 4567', 'abc', '+63 0917 123 4567'] as $invalid) {
            $this->assertNull(PhilippineMobileNumber::toE164($invalid), $invalid);
        }
        $this->assertNull(PhilippineMobileNumber::toE164(null));
    }
}
