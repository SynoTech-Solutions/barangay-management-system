<?php

namespace App\Domain\Sms;

use App\Enums\SmsEncoding;

final readonly class SmsLength
{
    public function __construct(
        public SmsEncoding $encoding,
        public int $units,
        public int $segments,
    ) {}

    public function exceeds(int $maxSegments): bool
    {
        return $this->segments > $maxSegments;
    }
}
