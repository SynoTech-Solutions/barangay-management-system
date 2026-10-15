<?php

namespace App\Domain\Finance;

use InvalidArgumentException;

final readonly class LiquidationSettlement
{
    public function __construct(
        public int $advanced,
        public int $actual,
    ) {
        if ($advanced < 0 || $actual < 0) {
            throw new InvalidArgumentException('Liquidation amounts cannot be negative.');
        }
    }

    public function refund(): int
    {
        return max(0, $this->advanced - $this->actual);
    }

    public function reimbursement(): int
    {
        return max(0, $this->actual - $this->advanced);
    }

    public function needsReceipt(): bool
    {
        return $this->refund() > 0;
    }
}
