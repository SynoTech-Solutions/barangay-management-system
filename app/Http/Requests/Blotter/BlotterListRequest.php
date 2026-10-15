<?php

namespace App\Http\Requests\Blotter;

use App\Enums\BlotterStatus;
use App\Queries\Filters\BlotterListFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlotterListRequest extends FormRequest
{
    private const int SEARCH_MAX = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.self::SEARCH_MAX],
            'status' => ['nullable', Rule::enum(BlotterStatus::class)],
            'incident_type_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function filters(): BlotterListFilters
    {
        return new BlotterListFilters(
            search: trim($this->string('search')->toString()),
            status: BlotterStatus::tryFrom($this->string('status')->toString()),
            incidentTypeId: $this->filled('incident_type_id') ? $this->integer('incident_type_id') : null,
            from: $this->filled('from') ? CarbonImmutable::parse($this->string('from')->toString())->startOfDay() : null,
            to: $this->filled('to') ? CarbonImmutable::parse($this->string('to')->toString())->endOfDay() : null,
        );
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
