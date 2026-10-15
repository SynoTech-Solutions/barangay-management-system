<?php

namespace App\Queries\Filters;

use App\Enums\BlotterStatus;
use Carbon\CarbonImmutable;

final readonly class BlotterListFilters
{
    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        public string $search = '',
        public ?BlotterStatus $status = null,
        public ?int $incidentTypeId = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}

    /**
     * @return array<string, string|int|null>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status?->value,
            'incident_type_id' => $this->incidentTypeId,
            'from' => $this->from?->format(self::DATE_FORMAT),
            'to' => $this->to?->format(self::DATE_FORMAT),
        ];
    }
}
