<?php

namespace App\Http\Requests\Finance;

use App\Enums\LiquidationStatus;
use App\Queries\Filters\LiquidationFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LiquidationListRequest extends FormRequest
{
    private const int SEARCH_MAX = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.self::SEARCH_MAX],
            'status' => ['nullable', Rule::enum(LiquidationStatus::class)],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function filters(): LiquidationFilters
    {
        return new LiquidationFilters(
            search: $this->string('search')->trim()->toString(),
            status: $this->enum('status', LiquidationStatus::class),
        );
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
