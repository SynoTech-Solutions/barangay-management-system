<?php

namespace App\Enums;

enum AccountGroupType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Other = 'other';

    public function labelKey(): string
    {
        return "finance.group_types.{$this->value}";
    }
}
