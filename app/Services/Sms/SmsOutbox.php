<?php

namespace App\Services\Sms;

use App\Domain\Sms\SmsSegments;
use App\Domain\Sms\SmsText;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Enums\LogEvent;
use App\Enums\SmsSource;
use App\Enums\SmsStatus;
use App\Exceptions\BmsException;
use App\Jobs\SendSmsMessage;
use App\Models\SmsBatch;
use App\Models\SmsGroup;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use App\Support\Config\SmsSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class SmsOutbox
{
    public function __construct(
        private AuditTrail $audit,
        private SmsSettings $settings,
    ) {}

    public function queue(RecipientSet $recipients, string $body, SmsSource $source, ?SmsGroup $group, User $actor): SmsBatch
    {
        $body = SmsText::clean($body);
        if ($body === '') {
            throw BmsException::of(ErrorCode::SmsEmptyBody);
        }

        $length = SmsSegments::of($body);
        if ($length->exceeds(SmsSegments::MAX_SEGMENTS)) {
            throw BmsException::of(ErrorCode::SmsTooLong, ['max' => SmsSegments::MAX_SEGMENTS]);
        }

        $this->ensureSendable($recipients);

        return DB::transaction(function () use ($recipients, $body, $length, $source, $group, $actor): SmsBatch {
            $now = CarbonImmutable::now();

            $batch = new SmsBatch([
                'source' => $source,
                'sms_group_id' => $group?->id,
                'recipient_count' => $recipients->count(),
                'skipped_count' => $recipients->skipped(),
                'segments' => $length->segments,
            ]);
            $batch->queued_by = $actor->id;
            $batch->save();

            $messageIds = [];
            foreach ($recipients->all() as $recipient) {
                $message = new SmsMessage([
                    'sms_batch_id' => $batch->id,
                    'resident_id' => $recipient->residentId,
                    'recipient' => $recipient->number,
                    'body' => $body,
                    'encoding' => $length->encoding,
                    'segments' => $length->segments,
                ]);
                $message->status = SmsStatus::Queued;
                $message->queued_by = $actor->id;
                $message->queued_at = $now;
                $message->save();
                $messageIds[] = $message->id;
            }

            $this->audit->record(AuditAction::SmsQueued, $batch, after: [
                'source' => $source->value,
                'sms_group_id' => $group?->id,
                ...$recipients->summary(),
                'segments' => $length->segments,
                'encoding' => $length->encoding->value,
            ], barangayId: $batch->barangay_id, actorId: $actor->id);

            foreach ($messageIds as $messageId) {
                SendSmsMessage::dispatch($messageId, $batch->barangay_id);
            }

            Log::info(LogEvent::SmsQueued->value, ['batch_id' => $batch->id, 'source' => $source->value, ...$recipients->summary()]);

            return $batch;
        });
    }

    public function ensureSendable(RecipientSet $recipients): void
    {
        if ($recipients->isEmpty()) {
            throw BmsException::of(ErrorCode::SmsNoRecipients, ['skipped' => $recipients->skipped()]);
        }

        if ($recipients->count() > $this->settings->maxRecipients) {
            throw BmsException::of(ErrorCode::SmsTooManyRecipients, ['count' => $recipients->count(), 'max' => $this->settings->maxRecipients]);
        }
    }
}
