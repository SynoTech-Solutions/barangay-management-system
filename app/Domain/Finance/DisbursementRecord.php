<?php

namespace App\Domain\Finance;

final readonly class DisbursementRecord
{
    public function __construct(
        public string $voucherNumber,
        public string $date,
        public string $payee,
        public string $particulars,
        public int $amount,
        public ?string $checkNumber = null,
        public ?string $liquidationNumber = null,
    ) {}
}
