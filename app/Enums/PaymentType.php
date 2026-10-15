<?php

namespace App\Enums;

enum PaymentType: string
{
    case CashAdvance = 'cash-advance';
    case Reimbursement = 'reimbursement';
    case Others = 'others';

    public function labelKey(): string
    {
        return "finance.payment_types.{$this->value}";
    }
}
