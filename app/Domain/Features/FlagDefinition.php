<?php

namespace App\Domain\Features;

use App\Enums\FeatureArea;
use App\Enums\FeatureFlag;
use App\Enums\FlagKind;

final readonly class FlagDefinition
{
    /**
     * @param  list<FeatureFlag>  $requires
     */
    public function __construct(
        public FeatureFlag $flag,
        public FeatureArea $area,
        public FlagKind $kind,
        public bool $defaultEnabled,
        public array $requires = [],
        public ?string $removeAfter = null,
    ) {}
}
