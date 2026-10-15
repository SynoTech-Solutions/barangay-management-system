<?php

namespace App\Services\Sms;

final class SmsErrorCodes
{
    public const string NOT_CONFIGURED = 'not_configured';

    public const string TIMEOUT = 'timeout';

    public const string GATEWAY_EXCEPTION = 'gateway_exception';

    public const string FEATURE_DISABLED = 'feature_disabled';

    public const string STUCK = 'stuck_in_sending';

    public const string JOB_FAILED = 'job_failed';

    private const string HTTP_PREFIX = 'http_';

    public static function http(int $status): string
    {
        return self::HTTP_PREFIX.$status;
    }
}
