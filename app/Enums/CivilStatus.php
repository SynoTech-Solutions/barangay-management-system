<?php

namespace App\Enums;

enum CivilStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Widowed = 'widowed';
    case Separated = 'separated';

    public function labelKey(): string
    {
        return "residents.civil_statuses.{$this->value}";
    }
}
