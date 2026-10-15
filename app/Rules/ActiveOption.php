<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

final readonly class ActiveOption implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(
        private string $model,
        private ?int $currentId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $usable = is_numeric($value) && $this->model::query()
            ->whereKey((int) $value)
            ->where(fn ($query) => $query->where('is_active', true)->when($this->currentId !== null, fn ($inner) => $inner->orWhereKey($this->currentId)))
            ->exists();

        if (! $usable) {
            $fail('validation.exists')->translate();
        }
    }
}
