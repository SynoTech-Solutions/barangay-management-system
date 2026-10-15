<?php

namespace App\Enums;

enum ReadinessStatus: string
{
    case Ok = 'ok';
    case Unavailable = 'unavailable';
}
