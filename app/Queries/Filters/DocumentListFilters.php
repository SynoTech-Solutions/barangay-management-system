<?php

namespace App\Queries\Filters;

use App\Domain\Residents\NameQuery;
use App\Enums\IssuedDocumentStatus;
use Carbon\CarbonImmutable;

final readonly class DocumentListFilters
{
    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        public NameQuery $name,
        public ?IssuedDocumentStatus $status = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public ?int $clearanceTypeId = null,
        public ?int $purposeId = null,
    ) {}

    /**
     * @return array<string, string|int|null>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->name->raw,
            'status' => $this->status?->value,
            'from' => $this->from?->format(self::DATE_FORMAT),
            'to' => $this->to?->format(self::DATE_FORMAT),
            'clearance_type_id' => $this->clearanceTypeId,
            'purpose_id' => $this->purposeId,
        ];
    }
}
