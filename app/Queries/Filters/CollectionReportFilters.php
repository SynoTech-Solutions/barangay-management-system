<?php

namespace App\Queries\Filters;

use App\Enums\CollectionSource;
use Carbon\CarbonImmutable;

final readonly class CollectionReportFilters
{
    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?CollectionSource $source = null,
    ) {}

    /**
     * @return array{from: string, to: string, source: string|null}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->format(self::DATE_FORMAT),
            'to' => $this->to->format(self::DATE_FORMAT),
            'source' => $this->source?->value,
        ];
    }
}
