<?php

namespace App\Enums;

enum ClearanceValidityMode: string
{
    case EndOfYear = 'end-of-year';
    case Months = 'months';
}
