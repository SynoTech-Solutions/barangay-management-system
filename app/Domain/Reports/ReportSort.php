<?php

namespace App\Domain\Reports;

use App\Enums\SortDirection;

final readonly class ReportSort
{
    public function __construct(
        public string $column,
        public SortDirection $direction,
    ) {}
}
