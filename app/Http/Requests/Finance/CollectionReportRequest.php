<?php

namespace App\Http\Requests\Finance;

use App\Enums\CollectionSource;
use App\Queries\Filters\CollectionReportFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CollectionReportRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'source' => ['nullable', Rule::enum(CollectionSource::class)],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function filters(CarbonImmutable $today): CollectionReportFilters
    {
        return new CollectionReportFilters(
            from: $this->filled('from') ? CarbonImmutable::parse($this->string('from')->toString())->startOfDay() : $today->startOfMonth(),
            to: $this->filled('to') ? CarbonImmutable::parse($this->string('to')->toString())->startOfDay() : $today->startOfDay(),
            source: CollectionSource::tryFrom($this->string('source')->toString()),
        );
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
