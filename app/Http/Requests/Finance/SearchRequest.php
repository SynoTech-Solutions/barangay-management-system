<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    private const int SEARCH_MAX = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.self::SEARCH_MAX],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function search(): string
    {
        return $this->string('search')->trim()->toString();
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
