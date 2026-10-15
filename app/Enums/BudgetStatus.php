<?php

namespace App\Enums;

enum BudgetStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';

    public function labelKey(): string
    {
        return "finance.budget_statuses.{$this->value}";
    }
}
