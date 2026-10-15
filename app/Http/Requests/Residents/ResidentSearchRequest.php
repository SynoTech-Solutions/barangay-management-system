<?php

namespace App\Http\Requests\Residents;

use App\Domain\Residents\NameQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResidentSearchRequest extends FormRequest
{
    private const int QUERY_MAX = 100;

    private const array BOOLEAN_VALUES = ['0', '1', 'true', 'false'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:'.self::QUERY_MAX],
            'include_deceased' => ['nullable', Rule::in(self::BOOLEAN_VALUES)],
            'exclude_id' => ['nullable', 'integer'],
            'for_issuance' => ['nullable', Rule::in(self::BOOLEAN_VALUES)],
            'with_mobile' => ['nullable', Rule::in(self::BOOLEAN_VALUES)],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function nameQuery(): NameQuery
    {
        return NameQuery::parse($this->string('q')->toString());
    }

    public function includeDeceased(): bool
    {
        return $this->boolean('include_deceased');
    }

    public function forIssuance(): bool
    {
        return $this->boolean('for_issuance');
    }

    public function withMobile(): bool
    {
        return $this->boolean('with_mobile');
    }

    public function excludeId(): ?int
    {
        return $this->filled('exclude_id') ? $this->integer('exclude_id') : null;
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
