<?php

namespace App\Queries\Filters;

use App\Enums\BusinessStatus;

final readonly class BusinessListFilters
{
    public function __construct(
        public string $search = '',
        public ?BusinessStatus $status = null,
        public ?int $categoryId = null,
        public ?int $natureId = null,
        public ?int $ownershipTypeId = null,
        public ?int $purokId = null,
    ) {}

    /**
     * @return array<string, string|int|null>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status?->value,
            'category_id' => $this->categoryId,
            'nature_id' => $this->natureId,
            'ownership_type_id' => $this->ownershipTypeId,
            'purok_id' => $this->purokId,
        ];
    }
}
