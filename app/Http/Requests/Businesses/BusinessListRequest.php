<?php

namespace App\Http\Requests\Businesses;

use App\Enums\BusinessStatus;
use App\Queries\Filters\BusinessListFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessListRequest extends FormRequest
{
    private const int SEARCH_MAX = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.self::SEARCH_MAX],
            'status' => ['nullable', Rule::enum(BusinessStatus::class)],
            'category_id' => ['nullable', 'integer'],
            'nature_id' => ['nullable', 'integer'],
            'ownership_type_id' => ['nullable', 'integer'],
            'purok_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function filters(): BusinessListFilters
    {
        return new BusinessListFilters(
            search: trim($this->string('search')->toString()),
            status: BusinessStatus::tryFrom($this->string('status')->toString()),
            categoryId: $this->optionalId('category_id'),
            natureId: $this->optionalId('nature_id'),
            ownershipTypeId: $this->optionalId('ownership_type_id'),
            purokId: $this->optionalId('purok_id'),
        );
    }

    public function perPage(): ?int
    {
        return $this->optionalId('per_page');
    }

    private function optionalId(string $field): ?int
    {
        return $this->filled($field) ? $this->integer($field) : null;
    }
}
