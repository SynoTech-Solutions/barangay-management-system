<?php

namespace App\Services\Sms;

use App\Support\Config\SmsSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Symfony\Component\HttpFoundation\Response as Status;

final readonly class HttpSmsGateway implements SmsGateway
{
    private const array RETRYABLE_CLIENT_STATUSES = [Status::HTTP_REQUEST_TIMEOUT, Status::HTTP_TOO_MANY_REQUESTS];

    private const array PROVIDER_ID_KEYS = ['id', 'message_id', 'messageId'];

    private const int PROVIDER_ID_MAX = 128;

    public function __construct(
        private SmsSettings $settings,
        private HttpFactory $http,
    ) {}

    public function send(OutgoingSms $message): SendResult
    {
        try {
            $response = $this->request()->post((string) $this->settings->httpUrl, array_filter([
                'to' => $message->recipient,
                'message' => $message->body,
                'sender' => $this->settings->httpSender,
                'reference' => (string) $message->messageId,
            ], fn (?string $value): bool => $value !== null));
        } catch (ConnectionException) {
            return SendResult::retryable(SmsErrorCodes::TIMEOUT);
        }

        return $this->map($response);
    }

    private function request(): PendingRequest
    {
        $request = $this->http
            ->withToken((string) $this->settings->httpToken)
            ->acceptJson()
            ->asJson()
            ->timeout($this->settings->httpTimeoutSeconds)
            ->connectTimeout($this->settings->httpConnectTimeoutSeconds);

        if ($this->settings->httpRetries > 0) {
            $request->retry(
                $this->settings->httpRetries + 1,
                $this->settings->httpRetryDelayMilliseconds,
                fn (mixed $exception): bool => $exception instanceof ConnectionException,
                throw: false,
            );
        }

        return $request;
    }

    private function map(Response $response): SendResult
    {
        $status = $response->status();

        if ($response->successful()) {
            return SendResult::accepted($this->providerId($response));
        }

        if ($response->clientError() && ! in_array($status, self::RETRYABLE_CLIENT_STATUSES, true)) {
            return SendResult::permanent(SmsErrorCodes::http($status));
        }

        return SendResult::retryable(SmsErrorCodes::http($status));
    }

    private function providerId(Response $response): ?string
    {
        $payload = $response->json();
        if (! is_array($payload)) {
            return null;
        }

        foreach (self::PROVIDER_ID_KEYS as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) || is_int($value)) {
                return mb_substr((string) $value, 0, self::PROVIDER_ID_MAX);
            }
        }

        return null;
    }
}
