<?php

namespace App\Enums;

enum FlagSource: string
{
    case Default = 'default';
    case GlobalOverride = 'global-override';
    case BarangayOverride = 'barangay-override';
    case ForcedOff = 'forced-off';
    case MissingDependency = 'missing-dependency';
}
