<?php

namespace App\Enums;

enum CollectionSource: string
{
    case BarangayClearance = 'barangay-clearance';
    case BusinessClearance = 'business-clearance';
    case Cedula = 'cedula';
    case IdCard = 'id-card';
    case IndigencyCertificate = 'indigency-certificate';
    case OtherCollection = 'other-collection';
    case FormTransaction = 'form-transaction';

    public function labelKey(): string
    {
        return "finance.collection_sources.{$this->value}";
    }
}
