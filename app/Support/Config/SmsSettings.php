<?php

namespace App\Support\Config;

use App\Enums\SmsDriver;
use SensitiveParameter;

final readonly class SmsSettings
{
    public function __construct(
        public SmsDriver $driver,
        public ?string $httpUrl,
        #[SensitiveParameter] public ?string $httpToken,
        public ?string $httpSender,
        public int $httpTimeoutSeconds,
        public int $httpConnectTimeoutSeconds,
        public int $httpRetries,
        public int $httpRetryDelayMilliseconds,
        public int $maxAttempts,
        public int $retryBaseSeconds,
        public int $retryMaxSeconds,
        public int $ratePerMinute,
        public int $stuckAfterMinutes,
        public int $maxRecipients,
        public int $retentionDays,
    ) {}

    public function keepsHistoryForever(): bool
    {
        return $this->retentionDays === 0;
    }
}
