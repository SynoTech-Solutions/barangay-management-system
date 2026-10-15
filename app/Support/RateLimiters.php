<?php

namespace App\Support;

final class RateLimiters
{
    public const string LOGIN = 'login';

    public const string TWO_FACTOR = 'two-factor';

    public const string PASSWORD_UPDATE = 'password-update';

    public const int SMS_WINDOW_SECONDS = 60;

    private const string SMS_SEND_PREFIX = 'sms-send:';

    public static function smsSend(int $barangayId): string
    {
        return self::SMS_SEND_PREFIX.$barangayId;
    }
}
