<?php

namespace App\Http\Requests\Forms;

use App\Domain\Residents\NameQuery;
use App\Enums\IssuedDocumentStatus;
use App\Queries\Filters\FormTransactionFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FormTransactionListRequest extends FormRequest
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
            'type_id' => ['nullable', 'integer'],
            'template_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function filters(): FormTransactionFilters
    {
        return new FormTransactionFilters(
            name: NameQuery::parse($this->string('search')->toString()),
            status: IssuedDocumentStatus::tryFrom($this->string('status')->toString()),
            from: $this->filled('from') ? CarbonImmutable::parse($this->string('from')->toString())->startOfDay() : null,
            to: $this->filled('to') ? CarbonImmutable::parse($this->string('to')->toString())->endOfDay() : null,
            typeId: $this->filled('type_id') ? $this->integer('type_id') : null,
            templateId: $this->filled('template_id') ? $this->integer('template_id') : null,
        );
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
