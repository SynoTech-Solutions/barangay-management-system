<?php

namespace App\Rules;

use App\Domain\Residents\PhilippineMobileNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PhilippineMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PhilippineMobileNumber::isValid($value)) {
            $fail('residents.validation.mobile_number')->translate();
        }
    }
}
