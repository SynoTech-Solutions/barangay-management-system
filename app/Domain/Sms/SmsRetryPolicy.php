<?php

namespace App\Domain\Sms;

final class SmsRetryPolicy
{
    private const int GROWTH = 2;

    public static function canRetry(int $attempts, int $maxAttempts): bool
    {
        return $attempts < $maxAttempts;
    }

    public static function delaySeconds(int $attempts, int $baseSeconds, int $maxSeconds): int
    {
        $exponent = max(0, $attempts - 1);

        return (int) min($maxSeconds, $baseSeconds * (self::GROWTH ** $exponent));
    }
}
