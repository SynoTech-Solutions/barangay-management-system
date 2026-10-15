<?php

namespace App\Enums;

enum ReportDataset: string
{
    case Residents = 'residents';
    case BarangayClearances = 'barangay-clearances';
    case BusinessClearances = 'business-clearances';
    case Cedulas = 'cedulas';
    case Collections = 'collections';
    case Vouchers = 'vouchers';
    case FormTransactions = 'form-transactions';

    public function labelKey(): string
    {
        return "reports.saved.datasets.{$this->value}";
    }
}
