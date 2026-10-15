<?php

namespace App\Queries\Filters;

use App\Enums\LiquidationStatus;

final readonly class LiquidationFilters
{
    public function __construct(
        public string $search = '',
        public ?LiquidationStatus $status = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status?->value,
        ];
    }
}
