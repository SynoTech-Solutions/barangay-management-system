<?php

namespace App\Services\Sms;

final readonly class SmsRecipient
{
    public function __construct(
        public string $number,
        public ?int $residentId = null,
    ) {}
}
