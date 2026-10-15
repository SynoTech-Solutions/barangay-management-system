<?php

namespace App\Enums;

enum ReportColumnType: string
{
    case Text = 'text';
    case Number = 'number';
    case Money = 'money';
    case Date = 'date';
    case Boolean = 'boolean';
    case Choice = 'choice';
}
