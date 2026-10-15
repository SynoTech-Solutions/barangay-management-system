<?php

namespace App\Rules;

use App\Models\Purok;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

final readonly class PurokOption implements ValidationRule
{
    public function __construct(private ?int $keepId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $valid = is_numeric($value) && Purok::query()
            ->whereKey((int) $value)
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereKey($this->keepId ?? 0))
            ->exists();

        if (! $valid) {
            $fail('validation.exists')->translate();
        }
    }
}
