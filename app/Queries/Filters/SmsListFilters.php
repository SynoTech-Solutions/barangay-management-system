<?php

namespace App\Queries\Filters;

use App\Enums\SmsStatus;
use Carbon\CarbonImmutable;

final readonly class SmsListFilters
{
    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        public ?string $search = null,
        public ?SmsStatus $status = null,
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
            'from' => $this->from?->format(self::DATE_FORMAT),
            'to' => $this->to?->format(self::DATE_FORMAT),
        ];
    }
}
