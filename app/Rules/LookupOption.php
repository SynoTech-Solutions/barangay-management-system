<?php

namespace App\Rules;

use App\Enums\LookupType;
use App\Models\LookupValue;
use App\Tenancy\CurrentBarangay;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

final readonly class LookupOption implements ValidationRule
{
    public function __construct(
        private LookupType $type,
        private ?int $keepId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $barangayId = app(CurrentBarangay::class)->id();

        $valid = is_numeric($value) && LookupValue::query()
            ->whereKey((int) $value)
            ->where('type', $this->type)
            ->where(fn (Builder $query) => $query->whereNull('barangay_id')->orWhere('barangay_id', $barangayId))
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereKey($this->keepId ?? 0))
            ->exists();

        if (! $valid) {
            $fail('validation.exists')->translate();
        }
    }
}
