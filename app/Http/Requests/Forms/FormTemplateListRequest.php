<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class FormTemplateListRequest extends FormRequest
{
    private const int SEARCH_MAX = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.self::SEARCH_MAX],
            'type_id' => ['nullable', 'integer'],
            'inactive' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function typeId(): ?int
    {
        return $this->filled('type_id') ? $this->integer('type_id') : null;
    }

    public function includeInactive(): bool
    {
        return $this->boolean('inactive');
    }

    public function search(): ?string
    {
        return $this->filled('search') ? $this->string('search')->trim()->toString() : null;
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }

    /**
     * @return array{search: string|null, type_id: int|null, inactive: bool}
     */
    public function filters(): array
    {
        return ['search' => $this->search(), 'type_id' => $this->typeId(), 'inactive' => $this->includeInactive()];
    }
}
