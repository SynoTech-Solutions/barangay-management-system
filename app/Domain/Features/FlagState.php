<?php

namespace App\Domain\Features;

use App\Enums\FeatureFlag;
use App\Enums\FlagSource;

final readonly class FlagState
{
    public function __construct(
        public FeatureFlag $flag,
        public bool $enabled,
        public FlagSource $source,
        public ?bool $configured = null,
        public ?FeatureFlag $blockedBy = null,
    ) {}
}
