<?php

namespace App\Domain\Reports;

use App\Enums\ReportColumnSensitivity;
use App\Enums\ReportColumnType;
use BackedEnum;

final readonly class ColumnDefinition
{
    /**
     * @param  class-string<BackedEnum>|null  $choices
     */
    public function __construct(
        public string $key,
        public ReportColumnType $type,
        public bool $groupable = false,
        public ReportColumnSensitivity $sensitivity = ReportColumnSensitivity::None,
        public ?string $choices = null,
    ) {}

    /**
     * @return list<string>
     */
    public function choiceValues(): array
    {
        if ($this->choices === null) {
            return [];
        }

        return array_map(fn (BackedEnum $case): string => (string) $case->value, ($this->choices)::cases());
    }
}
