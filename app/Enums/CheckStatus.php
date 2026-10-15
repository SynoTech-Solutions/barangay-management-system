<?php

namespace App\Enums;

enum CheckStatus: string
{
    case ForRelease = 'for-release';
    case Released = 'released';
    case Cancelled = 'cancelled';

    public function labelKey(): string
    {
        return "finance.check_statuses.{$this->value}";
    }
}
