<?php

namespace Tests\Feature\Sms;

use App\Enums\SendOutcome;
use App\Enums\SmsDriver;
use App\Enums\SmsSource;
use App\Enums\SmsStatus;
use App\Models\SmsMessage;
use App\Services\Sms\HttpSmsGateway;
use App\Services\Sms\OutgoingSms;
use App\Services\Sms\RecipientSet;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsOutbox;
use App\Services\Sms\SmsRecipient;
use App\Support\Config\SmsSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesSms;
use Tests\TestCase;

class HttpSmsGatewayTest extends TestCase
{
    use CreatesSms, RefreshDatabase;

    private const string URL = 'https://gateway.example.test/v1/messages';

    private const string TOKEN = 'tok-test-SECRET-0001';

    private function gateway(int $retries = 0): HttpSmsGateway
    {
        return new HttpSmsGateway(new SmsSettings(
            driver: SmsDriver::Http,
            httpUrl: self::URL,
            httpToken: self::TOKEN,
            httpSender: 'BARANGAY',
            httpTimeoutSeconds: 5,
            httpConnectTimeoutSeconds: 2,
            httpRetries: $retries,
            httpRetryDelayMilliseconds: 0,
            maxAttempts: 5,
            retryBaseSeconds: 60,
            retryMaxSeconds: 3600,
            ratePerMinute: 30,
            stuckAfterMinutes: 15,
            maxRecipients: 500,
            retentionDays: 0,
        ), app(HttpFactory::class));
    }

    private function message(): OutgoingSms
    {
        return new OutgoingSms(42, '+639170000001', 'Clean-up drive on Sunday.', 1);
    }

    /**
     * @return array<string, array{0: int, 1: SendOutcome, 2: string|null}>
     */
    public static function statuses(): array
    {
        return [
            'created' => [201, SendOutcome::Accepted, null],
            'bad request' => [400, SendOutcome::Permanent, 'http_400'],
            'unauthorized' => [401, SendOutcome::Permanent, 'http_401'],
            'unprocessable' => [422, SendOutcome::Permanent, 'http_422'],
            'too many requests' => [429, SendOutcome::Retryable, 'http_429'],
            'server error' => [500, SendOutcome::Retryable, 'http_500'],
            'unavailable' => [503, SendOutcome::Retryable, 'http_503'],
        ];
    }

    #[DataProvider('statuses')]
    public function test_status_codes_map_to_accepted_permanent_or_retryable(int $status, SendOutcome $outcome, ?string $code): void
    {
        Http::fake([self::URL => Http::response(['id' => 'gw-123'], $status)]);

        $result = $this->gateway()->send($this->message());

        $this->assertSame($outcome, $result->outcome);
        $this->assertSame($code, $result->errorCode);
        $this->assertSame($outcome === SendOutcome::Accepted ? 'gw-123' : null, $result->providerMessageId);
    }

    public function test_the_request_carries_the_token_number_text_sender_and_reference(): void
    {
        Http::fake([self::URL => Http::response(['message_id' => 77], 200)]);

        $result = $this->gateway()->send($this->message());

        $this->assertSame('77', $result->providerMessageId);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::URL
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer '.self::TOKEN)
            && $request['to'] === '+639170000001'
            && $request['message'] === 'Clean-up drive on Sunday.'
            && $request['sender'] === 'BARANGAY'
            && $request['reference'] === '42');
    }

    public function test_timeouts_are_retryable_and_connection_retries_are_configurable(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;

            throw new ConnectionException('Connection timed out');
        });

        $result = $this->gateway(retries: 2)->send($this->message());

        $this->assertSame(SendOutcome::Retryable, $result->outcome);
        $this->assertSame('timeout', $result->errorCode);
        $this->assertSame(3, $calls);
    }

    public function test_the_http_driver_is_selected_from_config_and_never_logs_the_token_or_the_text(): void
    {
        [$user] = $this->smsDesk();
        $this->configureSms(['driver' => 'http', 'http.url' => self::URL, 'http.token' => self::TOKEN]);
        $this->captureLogs();
        Http::fake([self::URL => Http::sequence()->push(['error' => 'down'], 500)->push(['id' => 'gw-9'], 200)]);

        $this->assertInstanceOf(HttpSmsGateway::class, app(SmsGateway::class));

        $set = new RecipientSet;
        $set->add(new SmsRecipient('+639170000001'));
        app(SmsOutbox::class)->queue($set, 'Private medical reminder.', SmsSource::Compose, null, $user);

        $message = SmsMessage::query()->sole();
        $this->assertSame(SmsStatus::Queued, $message->status);
        $this->assertSame('http_500', $message->last_error_code);

        $logs = $this->loggedText();
        $this->assertNotSame('[]', $logs);
        $this->assertStringNotContainsString(self::TOKEN, $logs);
        $this->assertStringNotContainsString('Private medical', $logs);
        $this->assertStringNotContainsString('9170000001', $logs);
        $this->assertStringContainsString('***0001', $logs);
    }
}
