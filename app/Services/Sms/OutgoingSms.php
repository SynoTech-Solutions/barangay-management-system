<?php

namespace App\Services\Sms;

final readonly class OutgoingSms
{
    public function __construct(
        public int $messageId,
        public string $recipient,
        public string $body,
        public int $segments,
    ) {}
}
