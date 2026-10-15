<?php

namespace App\Http\Requests\Finance;

use App\Queries\Filters\OtherCollectionFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class OtherCollectionListRequest extends FormRequest
{
    private const int SEARCH_MAX = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.self::SEARCH_MAX],
            'collection_type_id' => ['nullable', 'integer'],
            'include_cancelled' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function filters(): OtherCollectionFilters
    {
        return new OtherCollectionFilters(
            search: $this->string('search')->trim()->toString(),
            collectionTypeId: $this->filled('collection_type_id') ? $this->integer('collection_type_id') : null,
            includeCancelled: $this->boolean('include_cancelled'),
            from: $this->filled('from') ? CarbonImmutable::parse($this->string('from')->toString()) : null,
            to: $this->filled('to') ? CarbonImmutable::parse($this->string('to')->toString()) : null,
        );
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
