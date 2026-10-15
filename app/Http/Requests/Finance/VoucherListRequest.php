<?php

namespace App\Http\Requests\Finance;

use App\Enums\PaymentType;
use App\Enums\VoucherStatus;
use App\Queries\Filters\VoucherFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VoucherListRequest extends FormRequest
{
    private const int SEARCH_MAX = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.self::SEARCH_MAX],
            'status' => ['nullable', Rule::enum(VoucherStatus::class)],
            'payment_type' => ['nullable', Rule::enum(PaymentType::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer'],
        ];
    }

    public function filters(): VoucherFilters
    {
        return new VoucherFilters(
            search: $this->string('search')->trim()->toString(),
            status: $this->enum('status', VoucherStatus::class),
            paymentType: $this->enum('payment_type', PaymentType::class),
            from: $this->filled('from') ? CarbonImmutable::parse($this->string('from')->toString()) : null,
            to: $this->filled('to') ? CarbonImmutable::parse($this->string('to')->toString()) : null,
        );
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
