<?php

namespace App\Enums;

enum ReceiptDocumentType: string
{
    case BarangayClearance = 'barangay-clearance';
    case BusinessClearance = 'business-clearance';
    case IdCard = 'id-card';
    case OtherCollection = 'other-collection';
    case LiquidationRefund = 'liquidation-refund';
    case FormTransaction = 'form-transaction';
}
