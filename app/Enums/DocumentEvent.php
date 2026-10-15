<?php

namespace App\Enums;

enum DocumentEvent: string
{
    case Issued = 'issued';
    case Updated = 'updated';
    case Released = 'released';
    case Cancelled = 'cancelled';
    case Printed = 'printed';
}
