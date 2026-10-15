<?php

namespace App\Http\Requests\Documents;

use App\Domain\Residents\NameQuery;
use App\Enums\IssuedDocumentStatus;
use App\Queries\Filters\DocumentListFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentListRequest extends FormRequest
{
    private const int SEARCH_MAX = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.self::SEARCH_MAX],
            'status' => ['nullable', Rule::enum(IssuedDocumentStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'clearance_type_id' => ['nullable', 'integer'],
            'purpose_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function filters(): DocumentListFilters
    {
        return new DocumentListFilters(
            name: NameQuery::parse($this->string('search')->toString()),
            status: IssuedDocumentStatus::tryFrom($this->string('status')->toString()),
            from: $this->filled('from') ? CarbonImmutable::parse($this->string('from')->toString())->startOfDay() : null,
            to: $this->filled('to') ? CarbonImmutable::parse($this->string('to')->toString())->endOfDay() : null,
            clearanceTypeId: $this->filled('clearance_type_id') ? $this->integer('clearance_type_id') : null,
            purposeId: $this->filled('purpose_id') ? $this->integer('purpose_id') : null,
        );
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
