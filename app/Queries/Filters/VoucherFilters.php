<?php

namespace App\Queries\Filters;

use App\Enums\PaymentType;
use App\Enums\VoucherStatus;
use Carbon\CarbonImmutable;

final readonly class VoucherFilters
{
    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        public string $search = '',
        public ?VoucherStatus $status = null,
        public ?PaymentType $paymentType = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status?->value,
            'payment_type' => $this->paymentType?->value,
            'from' => $this->from?->format(self::DATE_FORMAT),
            'to' => $this->to?->format(self::DATE_FORMAT),
        ];
    }
}
