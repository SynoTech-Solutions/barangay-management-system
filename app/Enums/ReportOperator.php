<?php

namespace App\Enums;

enum ReportOperator: string
{
    case Equals = 'eq';
    case NotEquals = 'neq';
    case Contains = 'contains';
    case StartsWith = 'starts_with';
    case GreaterThan = 'gt';
    case AtLeast = 'gte';
    case LessThan = 'lt';
    case AtMost = 'lte';
    case IsTrue = 'is_true';
    case IsFalse = 'is_false';
    case IsEmpty = 'is_empty';
    case IsNotEmpty = 'is_not_empty';

    public function labelKey(): string
    {
        return "reports.saved.operators.{$this->value}";
    }

    public function needsValue(): bool
    {
        return ! in_array($this, [self::IsTrue, self::IsFalse, self::IsEmpty, self::IsNotEmpty], true);
    }

    /**
     * @return list<self>
     */
    public static function for(ReportColumnType $type): array
    {
        return match ($type) {
            ReportColumnType::Text => [self::Equals, self::NotEquals, self::Contains, self::StartsWith, self::IsEmpty, self::IsNotEmpty],
            ReportColumnType::Number, ReportColumnType::Money, ReportColumnType::Date => [self::Equals, self::NotEquals, self::GreaterThan, self::AtLeast, self::LessThan, self::AtMost, self::IsEmpty, self::IsNotEmpty],
            ReportColumnType::Boolean => [self::IsTrue, self::IsFalse],
            ReportColumnType::Choice => [self::Equals, self::NotEquals, self::IsEmpty, self::IsNotEmpty],
        };
    }
}
