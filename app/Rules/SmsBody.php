<?php

namespace App\Rules;

use App\Domain\Sms\SmsSegments;
use App\Domain\Sms\SmsText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class SmsBody implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $body = is_string($value) ? SmsText::clean($value) : '';

        if ($body === '') {
            $fail('sms.validation.body_required')->translate();

            return;
        }

        if (SmsSegments::of($body)->exceeds(SmsSegments::MAX_SEGMENTS)) {
            $fail('sms.validation.body_too_long')->translate(['max' => (string) SmsSegments::MAX_SEGMENTS]);
        }
    }
}
