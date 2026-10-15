<?php

namespace App\Domain\Reports;

use App\Enums\ReportColumnType;
use App\Enums\ReportOperator;
use DateTimeImmutable;

final class FilterValueRule
{
    public const int MAX_LENGTH = 100;

    private const string DATE_FORMAT = 'Y-m-d';

    private const string NUMBER_PATTERN = '/^-?\d{1,12}$/';

    private const string MONEY_PATTERN = '/^-?\d{1,12}(\.\d{1,2})?$/';

    public static function accepts(ColumnDefinition $column, ReportOperator $operator, ?string $value): bool
    {
        if (! in_array($operator, ReportOperator::for($column->type), true)) {
            return false;
        }

        if (! $operator->needsValue()) {
            return true;
        }

        if ($value === null || $value === '' || mb_strlen($value) > self::MAX_LENGTH) {
            return false;
        }

        return match ($column->type) {
            ReportColumnType::Text => true,
            ReportColumnType::Number => preg_match(self::NUMBER_PATTERN, $value) === 1,
            ReportColumnType::Money => preg_match(self::MONEY_PATTERN, $value) === 1,
            ReportColumnType::Date => ($date = DateTimeImmutable::createFromFormat('!'.self::DATE_FORMAT, $value)) !== false && $date->format(self::DATE_FORMAT) === $value,
            ReportColumnType::Choice => in_array($value, $column->choiceValues(), true),
            ReportColumnType::Boolean => false,
        };
    }
}
