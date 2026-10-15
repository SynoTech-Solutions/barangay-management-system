<?php

namespace App\Jobs;

use App\Models\Barangay;
use App\Services\Sms\SmsDelivery;
use App\Tenancy\CurrentBarangay;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendSmsMessage implements ShouldQueueAfterCommit
{
    use Queueable;

    public const int RETRY_WINDOW_HOURS = 24;

    public int $maxExceptions = 3;

    public function __construct(
        public readonly int $messageId,
        public readonly int $barangayId,
    ) {}

    public function retryUntil(): CarbonImmutable
    {
        return CarbonImmutable::now()->addHours(self::RETRY_WINDOW_HOURS);
    }

    public function handle(SmsDelivery $delivery, CurrentBarangay $currentBarangay): void
    {
        $barangay = Barangay::query()->find($this->barangayId);
        if ($barangay === null) {
            return;
        }

        $release = $currentBarangay->runAs($barangay, fn (): ?int => $delivery->attempt($this->messageId, $barangay));

        if ($release !== null) {
            $this->release($release);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(SmsDelivery::class)->abandon($this->messageId);
    }
}
