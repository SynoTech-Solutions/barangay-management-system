<?php

namespace App\Enums;

enum SmsEncoding: string
{
    case Gsm7 = 'gsm-7';
    case Ucs2 = 'ucs-2';
}
