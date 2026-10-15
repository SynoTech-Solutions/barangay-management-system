<?php

namespace App\Enums;

enum FeatureOverrideState: string
{
    case On = 'on';
    case Off = 'off';
    case Default = 'default';
}
