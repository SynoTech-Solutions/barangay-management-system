<?php

namespace App\Enums;

enum BusinessStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function labelKey(): string
    {
        return "businesses.statuses.{$this->value}";
    }
}
