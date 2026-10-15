<?php

namespace App\Domain\Finance;

use InvalidArgumentException;

final readonly class ConsumptionEntry
{
    public function __construct(
        public int $originalAmount,
        public ?int $actualAmount = null,
        public bool $cancelled = false,
    ) {
        if ($originalAmount < 0 || ($actualAmount !== null && $actualAmount < 0)) {
            throw new InvalidArgumentException('Consumed amounts cannot be negative.');
        }
    }

    public function effectiveAmount(): int
    {
        return $this->actualAmount ?? $this->originalAmount;
    }
}
