<?php

namespace App\Support\Config;

use App\Enums\FeatureFlag;

final readonly class FeatureSettings
{
    /**
     * @param  list<FeatureFlag>  $forcedOff
     */
    public function __construct(
        public array $forcedOff,
        public int $overrideCacheSeconds,
    ) {}
}
