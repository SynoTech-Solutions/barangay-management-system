<?php

namespace App\Rules;

use App\Services\Receipts\OfficialReceiptRegistry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

final readonly class AvailableOrNumber implements ValidationRule
{
    public function __construct(private ?Model $ignore = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && $value !== '' && OfficialReceiptRegistry::isTaken($value, $this->ignore)) {
            $fail('validation.unique')->translate();
        }
    }
}
