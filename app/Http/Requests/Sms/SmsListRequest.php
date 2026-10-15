<?php

namespace App\Http\Requests\Sms;

use App\Enums\SmsStatus;
use App\Queries\Filters\SmsListFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SmsListRequest extends FormRequest
{
    private const int SEARCH_MAX = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.self::SEARCH_MAX],
            'status' => ['nullable', Rule::enum(SmsStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function filters(): SmsListFilters
    {
        return new SmsListFilters(
            search: $this->filled('search') ? trim($this->string('search')->toString()) : null,
            status: SmsStatus::tryFrom($this->string('status')->toString()),
            from: $this->filled('from') ? CarbonImmutable::parse($this->string('from')->toString())->startOfDay() : null,
            to: $this->filled('to') ? CarbonImmutable::parse($this->string('to')->toString())->endOfDay() : null,
        );
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
