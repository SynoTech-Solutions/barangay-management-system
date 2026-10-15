<?php

namespace App\Enums;

enum SignatoryOffice: string
{
    case Secretary = 'secretary';
    case Treasurer = 'treasurer';
    case Bookkeeper = 'bookkeeper';
    case HeadOfAppropriations = 'head-of-appropriations';
    case PunongBarangay = 'punong-barangay';
    case MunicipalAccountant = 'municipal-accountant';
    case Mayor = 'mayor';

    public function labelKey(): string
    {
        return "signatories.{$this->value}";
    }
}
