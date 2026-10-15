<?php

namespace App\Domain\Features;

use App\Enums\FeatureFlag;
use App\Enums\FlagSource;

final class FlagResolver
{
    /**
     * @return array<string, FlagState>
     */
    public static function resolveAll(FlagInputs $inputs): array
    {
        $states = [];
        foreach (FeatureFlag::cases() as $flag) {
            self::resolveInto($flag, $inputs, $states);
        }

        return $states;
    }

    public static function resolve(FeatureFlag $flag, FlagInputs $inputs): FlagState
    {
        $states = [];

        return self::resolveInto($flag, $inputs, $states);
    }

    /**
     * @param  array<string, FlagState>  $states
     */
    private static function resolveInto(FeatureFlag $flag, FlagInputs $inputs, array &$states): FlagState
    {
        if (isset($states[$flag->value])) {
            return $states[$flag->value];
        }

        $own = self::ownState($flag, $inputs);

        if ($own->enabled) {
            foreach (FlagCatalog::definition($flag)->requires as $dependency) {
                if (! self::resolveInto($dependency, $inputs, $states)->enabled) {
                    $own = new FlagState($flag, false, FlagSource::MissingDependency, $own->configured, $dependency);
                    break;
                }
            }
        }

        return $states[$flag->value] = $own;
    }

    private static function ownState(FeatureFlag $flag, FlagInputs $inputs): FlagState
    {
        if ($inputs->isForcedOff($flag)) {
            return new FlagState($flag, false, FlagSource::ForcedOff, false);
        }

        if (array_key_exists($flag->value, $inputs->barangayOverrides)) {
            $value = $inputs->barangayOverrides[$flag->value];

            return new FlagState($flag, $value, FlagSource::BarangayOverride, $value);
        }

        if (array_key_exists($flag->value, $inputs->globalOverrides)) {
            $value = $inputs->globalOverrides[$flag->value];

            return new FlagState($flag, $value, FlagSource::GlobalOverride, $value);
        }

        $default = FlagCatalog::definition($flag)->defaultEnabled;

        return new FlagState($flag, $default, FlagSource::Default, $default);
    }
}
