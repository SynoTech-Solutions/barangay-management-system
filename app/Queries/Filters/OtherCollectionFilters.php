<?php

namespace App\Queries\Filters;

use Carbon\CarbonImmutable;

final readonly class OtherCollectionFilters
{
    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        public string $search = '',
        public ?int $collectionTypeId = null,
        public bool $includeCancelled = false,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}

    /**
     * @return array<string, string|int|bool|null>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'collection_type_id' => $this->collectionTypeId,
            'include_cancelled' => $this->includeCancelled,
            'from' => $this->from?->format(self::DATE_FORMAT),
            'to' => $this->to?->format(self::DATE_FORMAT),
        ];
    }
}
