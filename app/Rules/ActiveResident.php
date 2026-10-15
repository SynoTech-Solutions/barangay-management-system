<?php

namespace App\Rules;

use App\Models\Resident;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class ActiveResident implements ValidationRule
{
    public function __construct(private ?int $exceptId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $valid = is_numeric($value)
            && (int) $value !== $this->exceptId
            && Resident::query()->whereKey((int) $value)->exists();

        if (! $valid) {
            $fail('validation.exists')->translate();
        }
    }
}
