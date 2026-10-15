<?php

namespace App\Enums;

enum DocumentKind: string
{
    case Resident = 'resident';
    case BarangayClearance = 'barangay-clearance';
    case BarangayClearanceControl = 'barangay-clearance-control';
    case Business = 'business';
    case BusinessClearance = 'business-clearance';
    case BusinessClearanceControl = 'business-clearance-control';
    case Blotter = 'blotter';
    case Cedula = 'cedula';
    case IndigencyCertificate = 'indigency-certificate';
    case IdCard = 'id-card';
    case OtherCollection = 'other-collection';
    case FormTemplate = 'form-template';
    case FormTransaction = 'form-transaction';
    case DisbursementVoucher = 'disbursement-voucher';
    case LiquidationReport = 'liquidation-report';
    case Check = 'check';
}
