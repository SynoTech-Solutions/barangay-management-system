<?php

namespace App\Services\Sms;

use App\Domain\Sms\MaskedNumber;
use App\Domain\Sms\SmsRetryPolicy;
use App\Enums\FeatureFlag;
use App\Enums\LogEvent;
use App\Enums\SendOutcome;
use App\Enums\SmsStatus;
use App\Models\Barangay;
use App\Models\SmsMessage;
use App\Services\Features\FeatureFlags;
use App\Support\Config\SmsSettings;
use App\Support\RateLimiters;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class SmsDelivery
{
    private const int MINIMUM_RELEASE_SECONDS = 1;

    public function __construct(
        private SmsGateway $gateway,
        private SmsSettings $settings,
        private FeatureFlags $features,
        private RateLimiter $limiter,
    ) {}

    public function attempt(int $messageId, Barangay $barangay): ?int
    {
        $message = SmsMessage::query()->find($messageId);
        if ($message === null || $message->status !== SmsStatus::Queued) {
            Log::info(LogEvent::SmsSkipped->value, ['message_id' => $messageId, 'status' => $message?->status->value]);

            return null;
        }

        if (! $this->features->enabled(FeatureFlag::Sms, $barangay)) {
            $this->failQueued($messageId, SmsErrorCodes::FEATURE_DISABLED);

            return null;
        }

        $key = RateLimiters::smsSend($barangay->id);
        if ($this->limiter->tooManyAttempts($key, $this->settings->ratePerMinute)) {
            $wait = max(self::MINIMUM_RELEASE_SECONDS, $this->limiter->availableIn($key));
            Log::info(LogEvent::SmsRateLimited->value, ['message_id' => $messageId, 'release_seconds' => $wait]);

            return $wait;
        }
        $this->limiter->hit($key, RateLimiters::SMS_WINDOW_SECONDS);

        $claimed = $this->claim($messageId);
        if ($claimed === null) {
            Log::info(LogEvent::SmsSkipped->value, ['message_id' => $messageId]);

            return null;
        }

        return $this->record($claimed->id, $this->call($claimed));
    }

    public function abandon(int $messageId): void
    {
        DB::transaction(function () use ($messageId): void {
            $message = SmsMessage::acrossBarangays()->whereKey($messageId)->lockForUpdate()->first();
            if ($message === null || ! in_array($message->status, [SmsStatus::Queued, SmsStatus::Sending], true)) {
                return;
            }

            $this->markFailed($message, SmsErrorCodes::JOB_FAILED);
        });
    }

    /**
     * @return array{requeued: list<array{id: int, barangay_id: int}>, failed: int}
     */
    public function releaseStuck(CarbonImmutable $now): array
    {
        return DB::transaction(function () use ($now): array {
            $stuck = SmsMessage::acrossBarangays()
                ->where('status', SmsStatus::Sending->value)
                ->where('sending_started_at', '<', $now->subMinutes($this->settings->stuckAfterMinutes))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $requeued = [];
            $failed = 0;
            foreach ($stuck as $message) {
                if (SmsRetryPolicy::canRetry($message->attempts, $this->settings->maxAttempts)) {
                    $message->status = SmsStatus::Queued;
                    $message->last_error_code = SmsErrorCodes::STUCK;
                    $message->sending_started_at = null;
                    $message->save();
                    $requeued[] = ['id' => $message->id, 'barangay_id' => $message->barangay_id];
                } else {
                    $this->markFailed($message, SmsErrorCodes::STUCK);
                    $failed++;
                }
            }

            return ['requeued' => $requeued, 'failed' => $failed];
        });
    }

    private function claim(int $messageId): ?SmsMessage
    {
        return DB::transaction(function () use ($messageId): ?SmsMessage {
            $message = SmsMessage::query()->whereKey($messageId)->lockForUpdate()->first();
            if ($message === null || $message->status !== SmsStatus::Queued || $message->body === null || $message->recipient === null) {
                return null;
            }

            $message->status = SmsStatus::Sending;
            $message->attempts++;
            $message->sending_started_at = CarbonImmutable::now();
            $message->save();

            return $message;
        });
    }

    private function call(SmsMessage $message): SendResult
    {
        try {
            return $this->gateway->send(new OutgoingSms($message->id, (string) $message->recipient, (string) $message->body, $message->segments));
        } catch (Throwable $exception) {
            Log::warning(LogEvent::SmsRetryScheduled->value, ['message_id' => $message->id, 'exception' => $exception::class]);

            return SendResult::retryable(SmsErrorCodes::GATEWAY_EXCEPTION);
        }
    }

    private function record(int $messageId, SendResult $result): ?int
    {
        return DB::transaction(function () use ($messageId, $result): ?int {
            $message = SmsMessage::query()->whereKey($messageId)->lockForUpdate()->firstOrFail();
            $now = CarbonImmutable::now();
            $context = ['message_id' => $message->id, 'recipient' => MaskedNumber::of($message->recipient), 'attempts' => $message->attempts];

            if ($result->outcome === SendOutcome::Accepted) {
                $message->status = SmsStatus::Sent;
                $message->sent_at = $now;
                $message->provider_message_id = $result->providerMessageId;
                $message->last_error_code = null;
                $message->sending_started_at = null;
                $message->save();
                Log::info(LogEvent::SmsSent->value, $context);

                return null;
            }

            $code = (string) $result->errorCode;
            if ($result->outcome === SendOutcome::Retryable && SmsRetryPolicy::canRetry($message->attempts, $this->settings->maxAttempts)) {
                $delay = SmsRetryPolicy::delaySeconds($message->attempts, $this->settings->retryBaseSeconds, $this->settings->retryMaxSeconds);
                $message->status = SmsStatus::Queued;
                $message->last_error_code = $code;
                $message->sending_started_at = null;
                $message->save();
                Log::info(LogEvent::SmsRetryScheduled->value, [...$context, 'error_code' => $code, 'release_seconds' => $delay]);

                return $delay;
            }

            $this->markFailed($message, $code);
            Log::warning(LogEvent::SmsFailed->value, [...$context, 'error_code' => $code]);

            return null;
        });
    }

    private function failQueued(int $messageId, string $code): void
    {
        DB::transaction(function () use ($messageId, $code): void {
            $message = SmsMessage::query()->whereKey($messageId)->lockForUpdate()->first();
            if ($message !== null && $message->status === SmsStatus::Queued) {
                $this->markFailed($message, $code);
                Log::warning(LogEvent::SmsFailed->value, ['message_id' => $messageId, 'error_code' => $code]);
            }
        });
    }

    private function markFailed(SmsMessage $message, string $code): void
    {
        $message->status = SmsStatus::Failed;
        $message->failed_at = CarbonImmutable::now();
        $message->last_error_code = $code;
        $message->sending_started_at = null;
        $message->save();
    }
}
