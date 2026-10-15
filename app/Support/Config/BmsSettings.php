<?php

namespace App\Support\Config;

use App\Enums\FeatureFlag;
use App\Enums\SmsDriver;

final readonly class BmsSettings
{
    private const int MINIMUM_PASSWORD_LENGTH = 8;

    private const int MINIMUM_DOCUMENT_NUMBER_WIDTH = 1;

    private const int MAXIMUM_DOCUMENT_NUMBER_WIDTH = 12;

    private const int MINIMUM_ID_CARD_VALIDITY_MONTHS = 1;

    private const int MAXIMUM_ID_CARD_VALIDITY_MONTHS = 120;

    private const int MINIMUM_PDF_FILL_TIMEOUT_SECONDS = 1;

    private const int MAXIMUM_PDF_FILL_TIMEOUT_SECONDS = 300;

    private const int MAXIMUM_SMS_HTTP_TIMEOUT_SECONDS = 120;

    private const int MAXIMUM_SMS_HTTP_RETRIES = 5;

    private const int MAXIMUM_SMS_HTTP_RETRY_DELAY_MS = 10000;

    private const int MAXIMUM_SMS_SENDER_LENGTH = 11;

    private const int MAXIMUM_SMS_ATTEMPTS = 20;

    private const int MAXIMUM_SMS_RETRY_SECONDS = 86400;

    private const int MAXIMUM_SMS_RATE_PER_MINUTE = 10000;

    private const int MAXIMUM_SMS_STUCK_MINUTES = 1440;

    private const int MAXIMUM_SMS_RECIPIENTS = 10000;

    private const int MAXIMUM_SMS_RETENTION_DAYS = 3650;

    private const array SMS_HTTP_SCHEMES = ['http', 'https'];

    public function __construct(
        public FeatureSettings $features,
        public AuthSettings $auth,
        public PaginationSettings $pagination,
        public int $auditRetentionDays,
        public int $documentNumberWidth,
        public string $requestIdHeader,
        public int $idCardValidityMonths,
        public FormSettings $forms,
        public SmsSettings $sms,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $problems = [];

        $forcedOff = [];
        foreach (self::listOfStrings($config, 'features.forced_off') as $value) {
            $flag = FeatureFlag::tryFrom($value);
            if ($flag === null) {
                $problems[] = "features.forced_off contains unknown flag \"{$value}\"";

                continue;
            }
            $forcedOff[] = $flag;
        }

        $overrideCacheSeconds = self::integer($config, 'features.override_cache_seconds', 0, $problems);
        $loginAttempts = self::integer($config, 'auth.login_max_attempts_per_minute', 1, $problems);
        $twoFactorAttempts = self::integer($config, 'auth.two_factor_max_attempts_per_minute', 1, $problems);
        $passwordMinLength = self::integer($config, 'auth.password_min_length', self::MINIMUM_PASSWORD_LENGTH, $problems);
        $auditRetentionDays = self::integer($config, 'audit.retention_days', 0, $problems);
        $defaultPageSize = self::integer($config, 'pagination.default_page_size', 1, $problems);
        $maxPageSize = self::integer($config, 'pagination.max_page_size', 1, $problems);
        $numberWidth = self::integer($config, 'documents.number_width', self::MINIMUM_DOCUMENT_NUMBER_WIDTH, $problems);

        $idCardValidityMonths = self::integer($config, 'id_cards.validity_months', self::MINIMUM_ID_CARD_VALIDITY_MONTHS, $problems);
        if ($idCardValidityMonths > self::MAXIMUM_ID_CARD_VALIDITY_MONTHS) {
            $problems[] = 'id_cards.validity_months must be at most '.self::MAXIMUM_ID_CARD_VALIDITY_MONTHS;
        }

        $pdfFillTimeout = self::integer($config, 'forms.pdf_fill_timeout_seconds', self::MINIMUM_PDF_FILL_TIMEOUT_SECONDS, $problems);
        if ($pdfFillTimeout > self::MAXIMUM_PDF_FILL_TIMEOUT_SECONDS) {
            $problems[] = 'forms.pdf_fill_timeout_seconds must be at most '.self::MAXIMUM_PDF_FILL_TIMEOUT_SECONDS;
        }

        $qpdfBinary = data_get($config, 'forms.qpdf_binary', '');
        if (! is_string($qpdfBinary)) {
            $problems[] = 'forms.qpdf_binary must be a string';
            $qpdfBinary = '';
        } elseif ($qpdfBinary !== '' && ! str_starts_with($qpdfBinary, DIRECTORY_SEPARATOR)) {
            $problems[] = 'forms.qpdf_binary must be an absolute path';
        }

        if ($defaultPageSize > $maxPageSize) {
            $problems[] = 'pagination.default_page_size must not exceed pagination.max_page_size';
        }

        if ($numberWidth > self::MAXIMUM_DOCUMENT_NUMBER_WIDTH) {
            $problems[] = 'documents.number_width must be at most '.self::MAXIMUM_DOCUMENT_NUMBER_WIDTH;
        }

        $requestIdHeader = data_get($config, 'observability.request_id_header');
        if (! is_string($requestIdHeader) || $requestIdHeader === '') {
            $problems[] = 'observability.request_id_header must be a non-empty string';
            $requestIdHeader = '';
        }

        $sms = self::sms($config, $problems);

        if ($problems !== []) {
            throw InvalidConfiguration::withProblems($problems);
        }

        return new self(
            features: new FeatureSettings($forcedOff, $overrideCacheSeconds),
            auth: new AuthSettings(
                loginMaxAttemptsPerMinute: $loginAttempts,
                twoFactorMaxAttemptsPerMinute: $twoFactorAttempts,
                passwordMinLength: $passwordMinLength,
                passwordCheckBreached: (bool) data_get($config, 'auth.password_check_breached'),
            ),
            pagination: new PaginationSettings($defaultPageSize, $maxPageSize),
            auditRetentionDays: $auditRetentionDays,
            documentNumberWidth: $numberWidth,
            requestIdHeader: $requestIdHeader,
            idCardValidityMonths: $idCardValidityMonths,
            forms: new FormSettings($qpdfBinary === '' ? null : $qpdfBinary, $pdfFillTimeout),
            sms: $sms,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $problems
     */
    private static function sms(array $config, array &$problems): SmsSettings
    {
        $driverValue = data_get($config, 'sms.driver');
        $driver = is_string($driverValue) ? SmsDriver::tryFrom($driverValue) : null;
        if ($driver === null) {
            $problems[] = 'sms.driver must be one of: '.implode(', ', array_map(fn (SmsDriver $case): string => $case->value, SmsDriver::cases()));
            $driver = SmsDriver::Null;
        }

        $url = self::optionalString($config, 'sms.http.url', $problems);
        $token = self::optionalString($config, 'sms.http.token', $problems);
        $sender = self::optionalString($config, 'sms.http.sender', $problems);

        if ($driver === SmsDriver::Http) {
            $scheme = $url === null ? null : parse_url($url, PHP_URL_SCHEME);
            if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array($scheme, self::SMS_HTTP_SCHEMES, true)) {
                $problems[] = 'sms.http.url must be an http or https URL when sms.driver is http';
            }
            if ($token === null) {
                $problems[] = 'sms.http.token must be set when sms.driver is http';
            }
        }

        if ($sender !== null && mb_strlen($sender) > self::MAXIMUM_SMS_SENDER_LENGTH) {
            $problems[] = 'sms.http.sender must be at most '.self::MAXIMUM_SMS_SENDER_LENGTH.' characters';
        }

        $retryBase = self::bounded($config, 'sms.retry_base_seconds', 1, self::MAXIMUM_SMS_RETRY_SECONDS, $problems);
        $retryMax = self::bounded($config, 'sms.retry_max_seconds', 1, self::MAXIMUM_SMS_RETRY_SECONDS, $problems);
        if ($retryMax < $retryBase) {
            $problems[] = 'sms.retry_max_seconds must be at least sms.retry_base_seconds';
        }

        return new SmsSettings(
            driver: $driver,
            httpUrl: $url,
            httpToken: $token,
            httpSender: $sender,
            httpTimeoutSeconds: self::bounded($config, 'sms.http.timeout_seconds', 1, self::MAXIMUM_SMS_HTTP_TIMEOUT_SECONDS, $problems),
            httpConnectTimeoutSeconds: self::bounded($config, 'sms.http.connect_timeout_seconds', 1, self::MAXIMUM_SMS_HTTP_TIMEOUT_SECONDS, $problems),
            httpRetries: self::bounded($config, 'sms.http.retries', 0, self::MAXIMUM_SMS_HTTP_RETRIES, $problems),
            httpRetryDelayMilliseconds: self::bounded($config, 'sms.http.retry_delay_ms', 0, self::MAXIMUM_SMS_HTTP_RETRY_DELAY_MS, $problems),
            maxAttempts: self::bounded($config, 'sms.max_attempts', 1, self::MAXIMUM_SMS_ATTEMPTS, $problems),
            retryBaseSeconds: $retryBase,
            retryMaxSeconds: $retryMax,
            ratePerMinute: self::bounded($config, 'sms.rate_per_minute', 1, self::MAXIMUM_SMS_RATE_PER_MINUTE, $problems),
            stuckAfterMinutes: self::bounded($config, 'sms.stuck_after_minutes', 1, self::MAXIMUM_SMS_STUCK_MINUTES, $problems),
            maxRecipients: self::bounded($config, 'sms.max_recipients', 1, self::MAXIMUM_SMS_RECIPIENTS, $problems),
            retentionDays: self::bounded($config, 'sms.retention_days', 0, self::MAXIMUM_SMS_RETENTION_DAYS, $problems),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $problems
     */
    private static function optionalString(array $config, string $key, array &$problems): ?string
    {
        $value = data_get($config, $key, '');

        if (! is_string($value)) {
            $problems[] = "{$key} must be a string";

            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $problems
     */
    private static function bounded(array $config, string $key, int $minimum, int $maximum, array &$problems): int
    {
        $value = self::integer($config, $key, $minimum, $problems);

        if ($value > $maximum) {
            $problems[] = "{$key} must be at most {$maximum}";

            return $maximum;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private static function listOfStrings(array $config, string $key): array
    {
        $value = data_get($config, $key, []);

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $problems
     */
    private static function integer(array $config, string $key, int $minimum, array &$problems): int
    {
        $value = data_get($config, $key);

        if (! is_int($value)) {
            $problems[] = "{$key} must be an integer";

            return $minimum;
        }

        if ($value < $minimum) {
            $problems[] = "{$key} must be at least {$minimum}";

            return $minimum;
        }

        return $value;
    }
}
