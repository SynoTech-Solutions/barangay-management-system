<?php

namespace App\Services\Sms;

use App\Enums\SendOutcome;

final readonly class SendResult
{
    private function __construct(
        public SendOutcome $outcome,
        public ?string $providerMessageId = null,
        public ?string $errorCode = null,
    ) {}

    public static function accepted(?string $providerMessageId): self
    {
        return new self(SendOutcome::Accepted, providerMessageId: $providerMessageId);
    }

    public static function retryable(string $errorCode): self
    {
        return new self(SendOutcome::Retryable, errorCode: $errorCode);
    }

    public static function permanent(string $errorCode): self
    {
        return new self(SendOutcome::Permanent, errorCode: $errorCode);
    }

    public function isAccepted(): bool
    {
        return $this->outcome === SendOutcome::Accepted;
    }
}
