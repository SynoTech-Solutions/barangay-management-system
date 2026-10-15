<?php

namespace Tests\Unit\Config;

use App\Enums\FeatureFlag;
use App\Enums\SmsDriver;
use App\Support\Config\BmsSettings;
use App\Support\Config\InvalidConfiguration;
use PHPUnit\Framework\TestCase;

class BmsSettingsTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function valid(): array
    {
        return [
            'features' => ['forced_off' => ['sms'], 'override_cache_seconds' => 60],
            'auth' => [
                'login_max_attempts_per_minute' => 5,
                'two_factor_max_attempts_per_minute' => 5,
                'password_min_length' => 12,
                'password_check_breached' => true,
            ],
            'audit' => ['retention_days' => 0],
            'pagination' => ['default_page_size' => 25, 'max_page_size' => 100],
            'documents' => ['number_width' => 6],
            'id_cards' => ['validity_months' => 12],
            'forms' => ['qpdf_binary' => '', 'pdf_fill_timeout_seconds' => 30],
            'sms' => [
                'driver' => 'log',
                'http' => [
                    'url' => '',
                    'token' => '',
                    'sender' => '',
                    'timeout_seconds' => 10,
                    'connect_timeout_seconds' => 5,
                    'retries' => 0,
                    'retry_delay_ms' => 500,
                ],
                'max_attempts' => 5,
                'retry_base_seconds' => 60,
                'retry_max_seconds' => 3600,
                'rate_per_minute' => 30,
                'stuck_after_minutes' => 15,
                'max_recipients' => 500,
                'retention_days' => 0,
            ],
            'observability' => ['request_id_header' => 'X-Request-Id'],
        ];
    }

    public function test_valid_configuration_builds_typed_settings(): void
    {
        $settings = BmsSettings::fromArray($this->valid());

        $this->assertSame([FeatureFlag::Sms], $settings->features->forcedOff);
        $this->assertSame(100, $settings->pagination->clamp(500));
        $this->assertSame(25, $settings->pagination->clamp(null));
        $this->assertSame(10, $settings->pagination->clamp(10));
    }

    public function test_id_card_validity_months_must_be_within_range(): void
    {
        $this->assertSame(12, BmsSettings::fromArray($this->valid())->idCardValidityMonths);

        foreach ([0, 121, '12'] as $invalid) {
            $config = $this->valid();
            $config['id_cards']['validity_months'] = $invalid;

            try {
                BmsSettings::fromArray($config);
                $this->fail('Expected invalid configuration for '.var_export($invalid, true));
            } catch (InvalidConfiguration $exception) {
                $this->assertStringContainsString('id_cards.validity_months', $exception->getMessage());
            }
        }
    }

    public function test_unknown_forced_off_flag_fails_fast(): void
    {
        $config = $this->valid();
        $config['features']['forced_off'] = ['no-such-flag'];

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('no-such-flag');

        BmsSettings::fromArray($config);
    }

    public function test_all_problems_are_reported_together(): void
    {
        $config = $this->valid();
        $config['auth']['password_min_length'] = 4;
        $config['pagination']['default_page_size'] = 500;

        try {
            BmsSettings::fromArray($config);
            $this->fail('Expected invalid configuration');
        } catch (InvalidConfiguration $exception) {
            $this->assertStringContainsString('auth.password_min_length', $exception->getMessage());
            $this->assertStringContainsString('pagination.default_page_size', $exception->getMessage());
        }
    }

    public function test_sms_defaults_use_the_log_driver_and_keep_history(): void
    {
        $sms = BmsSettings::fromArray($this->valid())->sms;

        $this->assertSame(SmsDriver::Log, $sms->driver);
        $this->assertNull($sms->httpUrl);
        $this->assertNull($sms->httpToken);
        $this->assertTrue($sms->keepsHistoryForever());
    }

    public function test_unknown_sms_driver_fails_fast(): void
    {
        $config = $this->valid();
        $config['sms']['driver'] = 'modem';

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage('sms.driver must be one of: log, http, null');

        BmsSettings::fromArray($config);
    }

    public function test_http_driver_needs_a_url_and_a_token_without_echoing_them(): void
    {
        $config = $this->valid();
        $config['sms']['driver'] = 'http';
        $config['sms']['http']['url'] = 'ftp://gateway.example.test/send?key=secret-in-url';

        try {
            BmsSettings::fromArray($config);
            $this->fail('Expected invalid configuration');
        } catch (InvalidConfiguration $exception) {
            $this->assertStringContainsString('sms.http.url must be an http or https URL', $exception->getMessage());
            $this->assertStringContainsString('sms.http.token must be set', $exception->getMessage());
            $this->assertStringNotContainsString('secret-in-url', $exception->getMessage());
        }

        $config['sms']['http']['url'] = 'https://gateway.example.test/messages';
        $config['sms']['http']['token'] = 'test-token-value';
        $config['sms']['http']['sender'] = 'BARANGAY';
        $sms = BmsSettings::fromArray($config)->sms;

        $this->assertSame(SmsDriver::Http, $sms->driver);
        $this->assertSame('https://gateway.example.test/messages', $sms->httpUrl);
        $this->assertSame('BARANGAY', $sms->httpSender);
    }

    public function test_sms_limits_are_range_checked(): void
    {
        $config = $this->valid();
        $config['sms']['http']['sender'] = 'SENDERNAMETOOLONG';
        $config['sms']['http']['timeout_seconds'] = 0;
        $config['sms']['http']['retries'] = 9;
        $config['sms']['max_attempts'] = 0;
        $config['sms']['retry_base_seconds'] = 600;
        $config['sms']['retry_max_seconds'] = 60;
        $config['sms']['rate_per_minute'] = 0;
        $config['sms']['max_recipients'] = 20000;
        $config['sms']['retention_days'] = -1;

        try {
            BmsSettings::fromArray($config);
            $this->fail('Expected invalid configuration');
        } catch (InvalidConfiguration $exception) {
            foreach ([
                'sms.http.sender must be at most 11',
                'sms.http.timeout_seconds must be at least 1',
                'sms.http.retries must be at most 5',
                'sms.max_attempts must be at least 1',
                'sms.retry_max_seconds must be at least sms.retry_base_seconds',
                'sms.rate_per_minute must be at least 1',
                'sms.max_recipients must be at most 10000',
                'sms.retention_days must be at least 0',
            ] as $problem) {
                $this->assertStringContainsString($problem, $exception->getMessage());
            }
        }
    }
}
