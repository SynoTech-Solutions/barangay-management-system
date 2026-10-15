<?php

namespace App\Enums;

enum LiquidationStatus: string
{
    case ForClearing = 'for-clearing';
    case Cleared = 'cleared';

    public function labelKey(): string
    {
        return "finance.liquidation_statuses.{$this->value}";
    }
}
