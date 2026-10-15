<?php

namespace App\Http\Requests\Finance;

use App\Enums\CheckStatus;
use App\Queries\Filters\CheckFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckListRequest extends FormRequest
{
    private const int SEARCH_MAX = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.self::SEARCH_MAX],
            'status' => ['nullable', Rule::enum(CheckStatus::class)],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function filters(): CheckFilters
    {
        return new CheckFilters(
            search: $this->string('search')->trim()->toString(),
            status: $this->enum('status', CheckStatus::class),
        );
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
