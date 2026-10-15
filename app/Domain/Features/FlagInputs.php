<?php

namespace App\Domain\Features;

use App\Enums\FeatureFlag;

final readonly class FlagInputs
{
    /**
     * @param  list<FeatureFlag>  $forcedOff
     * @param  array<string, bool>  $globalOverrides
     * @param  array<string, bool>  $barangayOverrides
     */
    public function __construct(
        public array $forcedOff = [],
        public array $globalOverrides = [],
        public array $barangayOverrides = [],
    ) {}

    public function isForcedOff(FeatureFlag $flag): bool
    {
        return in_array($flag, $this->forcedOff, true);
    }
}
