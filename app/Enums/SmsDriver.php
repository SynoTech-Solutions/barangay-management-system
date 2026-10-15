<?php

namespace App\Enums;

enum SmsDriver: string
{
    case Log = 'log';
    case Http = 'http';
    case Null = 'null';
}
