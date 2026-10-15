<?php

return [

    'features' => [
        'forced_off' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('BMS_FEATURES_FORCED_OFF', '')),
        ))),
        'override_cache_seconds' => (int) env('BMS_FEATURES_OVERRIDE_CACHE_SECONDS', 60),
    ],

    'auth' => [
        'login_max_attempts_per_minute' => (int) env('BMS_LOGIN_MAX_ATTEMPTS_PER_MINUTE', 5),
        'two_factor_max_attempts_per_minute' => (int) env('BMS_TWO_FACTOR_MAX_ATTEMPTS_PER_MINUTE', 5),
        'password_min_length' => (int) env('BMS_PASSWORD_MIN_LENGTH', 12),
        'password_check_breached' => (bool) env('BMS_PASSWORD_CHECK_BREACHED', true),
    ],

    'audit' => [
        'retention_days' => (int) env('BMS_AUDIT_RETENTION_DAYS', 0),
    ],

    'pagination' => [
        'default_page_size' => (int) env('BMS_DEFAULT_PAGE_SIZE', 25),
        'max_page_size' => (int) env('BMS_MAX_PAGE_SIZE', 100),
    ],

    'documents' => [
        'number_width' => (int) env('BMS_DOCUMENT_NUMBER_WIDTH', 6),
    ],

    'id_cards' => [
        'validity_months' => (int) env('BMS_ID_CARD_VALIDITY_MONTHS', 12),
    ],

    'forms' => [
        'qpdf_binary' => (string) env('BMS_PDF_QPDF_BINARY', ''),
        'pdf_fill_timeout_seconds' => (int) env('BMS_PDF_FILL_TIMEOUT_SECONDS', 30),
    ],

    'sms' => [
        'driver' => (string) env('BMS_SMS_DRIVER', 'log'),
        'http' => [
            'url' => (string) env('BMS_SMS_HTTP_URL', ''),
            'token' => (string) env('BMS_SMS_HTTP_TOKEN', ''),
            'sender' => (string) env('BMS_SMS_HTTP_SENDER', ''),
            'timeout_seconds' => (int) env('BMS_SMS_HTTP_TIMEOUT_SECONDS', 10),
            'connect_timeout_seconds' => (int) env('BMS_SMS_HTTP_CONNECT_TIMEOUT_SECONDS', 5),
            'retries' => (int) env('BMS_SMS_HTTP_RETRIES', 0),
            'retry_delay_ms' => (int) env('BMS_SMS_HTTP_RETRY_DELAY_MS', 500),
        ],
        'max_attempts' => (int) env('BMS_SMS_MAX_ATTEMPTS', 5),
        'retry_base_seconds' => (int) env('BMS_SMS_RETRY_BASE_SECONDS', 60),
        'retry_max_seconds' => (int) env('BMS_SMS_RETRY_MAX_SECONDS', 3600),
        'rate_per_minute' => (int) env('BMS_SMS_RATE_PER_MINUTE', 30),
        'stuck_after_minutes' => (int) env('BMS_SMS_STUCK_AFTER_MINUTES', 15),
        'max_recipients' => (int) env('BMS_SMS_MAX_RECIPIENTS', 500),
        'retention_days' => (int) env('BMS_SMS_RETENTION_DAYS', 0),
    ],

    'observability' => [
        'request_id_header' => env('BMS_REQUEST_ID_HEADER', 'X-Request-Id'),
    ],

];
