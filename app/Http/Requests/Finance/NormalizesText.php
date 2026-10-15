<?php

namespace App\Http\Requests\Finance;

use App\Domain\Residents\NameNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @mixin FormRequest
 */
trait NormalizesText
{
    /**
     * @param  list<string>  $fields
     */
    protected function normalizeText(array $fields): void
    {
        $normalized = [];
        foreach ($fields as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = NameNormalizer::normalize($this->input($field));
            }
        }

        $this->merge($normalized);
    }

    protected function optionalText(string $field): ?string
    {
        return $this->filled($field) ? $this->string($field)->toString() : null;
    }

    /**
     * @param  class-string<Model>  $model
     * @param  literal-string  $column
     */
    protected function uniqueIgnoringCase(string $model, string $column, ?int $exceptId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($model, $column, $exceptId): void {
            $taken = is_string($value) && $model::query()
                ->whereRaw("lower({$column}) = lower(?)", [$value])
                ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
                ->exists();

            if ($taken) {
                $fail('validation.unique')->translate();
            }
        };
    }
}
