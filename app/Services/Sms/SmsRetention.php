<?php

namespace App\Services\Sms;

use App\Enums\AuditAction;
use App\Enums\LogEvent;
use App\Enums\SmsStatus;
use App\Models\SmsMessage;
use App\Services\Audit\AuditTrail;
use App\Support\Config\SmsSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class SmsRetention
{
    private const string FINISHED_AT = 'coalesce(sent_at, failed_at, cancelled_at, queued_at)';

    public function __construct(
        private SmsSettings $settings,
        private AuditTrail $audit,
    ) {}

    /**
     * @return array<int, int>
     */
    public function prune(CarbonImmutable $now): array
    {
        if ($this->settings->keepsHistoryForever()) {
            return [];
        }

        $cutoff = $now->subDays($this->settings->retentionDays);

        return DB::transaction(function () use ($cutoff, $now): array {
            /** @var array<int, int> $counts */
            $counts = $this->eligible($cutoff)
                ->selectRaw('barangay_id, count(*) as pruned')
                ->groupBy('barangay_id')
                ->pluck('pruned', 'barangay_id')
                ->map(fn (mixed $count): int => (int) $count)
                ->all();

            if ($counts === []) {
                return [];
            }

            $this->eligible($cutoff)->update([
                'recipient' => null,
                'body' => null,
                'resident_id' => null,
                'provider_message_id' => null,
                'pruned_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($counts as $barangayId => $count) {
                $this->audit->record(AuditAction::SmsPruned, after: ['messages' => $count, 'retention_days' => $this->settings->retentionDays], barangayId: $barangayId);
            }

            Log::info(LogEvent::SmsPruned->value, ['messages' => array_sum($counts), 'barangays' => count($counts)]);

            return $counts;
        });
    }

    /**
     * @return Builder<SmsMessage>
     */
    private function eligible(CarbonImmutable $cutoff): Builder
    {
        return SmsMessage::acrossBarangays()
            ->whereIn('status', array_map(fn (SmsStatus $status): string => $status->value, SmsStatus::finished()))
            ->whereNull('pruned_at')
            ->whereRaw(self::FINISHED_AT.' < ?', [$cutoff]);
    }
}
