<?php

namespace App\Domain\Cedula;

use InvalidArgumentException;

final readonly class IndividualTaxInput
{
    public function __construct(
        public int $taxYear,
        public bool $exempt,
        public int $businessGrossReceipts,
        public int $salaries,
        public int $realPropertyIncome,
    ) {
        if ($businessGrossReceipts < 0 || $salaries < 0 || $realPropertyIncome < 0) {
            throw new InvalidArgumentException('Community tax income amounts cannot be negative.');
        }
    }
}
