<?php

namespace App\Enums;

enum FlagKind: string
{
    case Module = 'module';
    case Option = 'option';
    case Release = 'release';
    case Ops = 'ops';

    public function requiresRemovalDate(): bool
    {
        return $this === self::Release;
    }
}
