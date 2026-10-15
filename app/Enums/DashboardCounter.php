<?php

namespace App\Enums;

enum DashboardCounter: string
{
    case ClearancesForRelease = 'clearances-for-release';
    case ClearancesIssuedToday = 'clearances-issued-today';
    case BusinessClearancesForRelease = 'business-clearances-for-release';
    case BusinessClearancesIssuedToday = 'business-clearances-issued-today';
    case BusinessClearancesExpired = 'business-clearances-expired';
    case IdCardsForRelease = 'id-cards-for-release';
    case IdCardsIssuedToday = 'id-cards-issued-today';
    case CedulasForRelease = 'cedulas-for-release';
    case CedulasIssuedToday = 'cedulas-issued-today';
    case OpenBlotters = 'open-blotters';
    case FormTransactionsForRelease = 'form-transactions-for-release';
    case VouchersForVerification = 'vouchers-for-verification';
    case VouchersForApproval = 'vouchers-for-approval';
    case ChecksForRelease = 'checks-for-release';
    case UnliquidatedCashAdvances = 'unliquidated-cash-advances';
    case LiquidationsForClearing = 'liquidations-for-clearing';
    case SmsQueued = 'sms-queued';
    case SmsFailed = 'sms-failed';

    public function labelKey(): string
    {
        return "dashboard.counters.{$this->value}";
    }
}
