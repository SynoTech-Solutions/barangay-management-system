<?php

namespace App\Console\Commands;

use App\Enums\LogEvent;
use App\Jobs\SendSmsMessage;
use App\Services\Sms\SmsDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchStuckSmsCommand extends Command
{
    protected $signature = 'bms:sms:dispatch-stuck';

    protected $description = 'Requeue text messages stuck in "sending" longer than BMS_SMS_STUCK_AFTER_MINUTES; fail those out of attempts';

    public function handle(SmsDelivery $delivery): int
    {
        $result = DB::transaction(function () use ($delivery): array {
            $result = $delivery->releaseStuck(CarbonImmutable::now());
            foreach ($result['requeued'] as $message) {
                SendSmsMessage::dispatch($message['id'], $message['barangay_id']);
            }

            return $result;
        });

        $requeued = count($result['requeued']);
        Log::info(LogEvent::SmsStuckRequeued->value, ['requeued' => $requeued, 'failed' => $result['failed']]);
        $this->components->info("Requeued {$requeued} stuck message(s); marked {$result['failed']} as failed.");

        return self::SUCCESS;
    }
}
