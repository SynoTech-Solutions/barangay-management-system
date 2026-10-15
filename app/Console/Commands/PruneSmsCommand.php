<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsRetention;
use App\Support\Config\SmsSettings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class PruneSmsCommand extends Command
{
    protected $signature = 'bms:sms:prune';

    protected $description = 'Erase numbers and bodies of finished text messages older than BMS_SMS_RETENTION_DAYS, keeping counts and statuses';

    public function handle(SmsRetention $retention, SmsSettings $settings): int
    {
        if ($settings->keepsHistoryForever()) {
            $this->components->info('BMS_SMS_RETENTION_DAYS is 0: message history is kept.');

            return self::SUCCESS;
        }

        $counts = $retention->prune(CarbonImmutable::now());
        $total = array_sum($counts);
        $this->components->info("Pruned {$total} message(s) in ".count($counts).' barangay(s).');

        return self::SUCCESS;
    }
}
