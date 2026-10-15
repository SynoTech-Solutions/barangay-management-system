<?php

namespace App\Enums;

enum BlotterStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';

    public function labelKey(): string
    {
        return "blotters.statuses.{$this->value}";
    }
}
