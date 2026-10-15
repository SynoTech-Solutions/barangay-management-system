<?php

namespace App\Domain\Reports;

use App\Enums\ReportOperator;

final readonly class ReportFilter
{
    public function __construct(
        public string $column,
        public ReportOperator $operator,
        public ?string $value = null,
    ) {}
}
