<?php

namespace App\Domain\Features;

use App\Enums\FeatureArea;

final class FlagOwners
{
    public static function for(FeatureArea $area): string
    {
        return match ($area) {
            FeatureArea::Platform, FeatureArea::Migration => 'platform-maintainers',
            FeatureArea::Residents => 'residents-maintainers',
            FeatureArea::Sms => 'sms-maintainers',
            FeatureArea::Certificates => 'certificates-maintainers',
            FeatureArea::Forms => 'forms-maintainers',
            FeatureArea::Finance => 'finance-maintainers',
        };
    }
}
