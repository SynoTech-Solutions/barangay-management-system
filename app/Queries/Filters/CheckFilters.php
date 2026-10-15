<?php

namespace App\Queries\Filters;

use App\Enums\CheckStatus;

final readonly class CheckFilters
{
    public function __construct(
        public string $search = '',
        public ?CheckStatus $status = null,
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
