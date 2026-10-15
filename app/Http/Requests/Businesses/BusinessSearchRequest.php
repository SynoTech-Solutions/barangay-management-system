<?php

namespace App\Http\Requests\Businesses;

use Illuminate\Foundation\Http\FormRequest;

class BusinessSearchRequest extends FormRequest
{
    private const int QUERY_MAX = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.self::QUERY_MAX],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function term(): string
    {
        return trim($this->string('q')->toString());
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
