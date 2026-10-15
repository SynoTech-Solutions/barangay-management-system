<?php

namespace App\Domain\Geography;

enum PsgcLevel: string
{
    case Region = 'Reg';
    case Province = 'Prov';
    case City = 'City';
    case Municipality = 'Mun';
    case SubMunicipality = 'SubMun';
    case Barangay = 'Bgy';
}
