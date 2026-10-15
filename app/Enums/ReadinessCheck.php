<?php

namespace App\Enums;

enum ReadinessCheck: string
{
    case Database = 'database';
    case Cache = 'cache';
}
