<?php

namespace App\Services\Sms;

use App\Domain\Sms\MaskedNumber;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Enums\SmsStatus;
use App\Exceptions\BmsException;
use App\Jobs\SendSmsMessage;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class SmsMessageActions
{
    private const string ID_PREFIX = '#';

    private const string ID_SEPARATOR = ', ';

    public function __construct(private AuditTrail $audit) {}

    /**
     * @param  list<int>  $ids
     */
    public function cancel(array $ids, User $actor): int
    {
        return DB::transaction(function () use ($ids, $actor): int {
            $messages = $this->locked($ids);
            $this->ensureAll($messages, fn (SmsMessage $message): bool => $message->status === SmsStatus::Queued, ErrorCode::SmsMessagesNotQueued);

            $now = CarbonImmutable::now();
            foreach ($messages as $message) {
                $message->status = SmsStatus::Cancelled;
                $message->cancelled_at = $now;
                $message->cancelled_by = $actor->id;
                $message->save();

                $this->audit->record(AuditAction::SmsCancelled, $message, ['status' => SmsStatus::Queued->value], [
                    'status' => SmsStatus::Cancelled->value,
                    'recipient' => MaskedNumber::of($message->recipient),
                ], $message->barangay_id, $actor->id);
            }

            return $messages->count();
        });
    }

    /**
     * @param  list<int>  $ids
     */
    public function retry(array $ids, User $actor): int
    {
        return DB::transaction(function () use ($ids, $actor): int {
            $messages = $this->locked($ids);
            $this->ensureAll(
                $messages,
                fn (SmsMessage $message): bool => $message->status === SmsStatus::Failed && $message->body !== null && $message->recipient !== null,
                ErrorCode::SmsMessagesNotFailed,
            );

            foreach ($messages as $message) {
                $before = ['status' => SmsStatus::Failed->value, 'attempts' => $message->attempts, 'last_error_code' => $message->last_error_code];
                $message->status = SmsStatus::Queued;
                $message->attempts = 0;
                $message->failed_at = null;
                $message->last_error_code = null;
                $message->save();

                $this->audit->record(AuditAction::SmsRetried, $message, $before, [
                    'status' => SmsStatus::Queued->value,
                    'recipient' => MaskedNumber::of($message->recipient),
                ], $message->barangay_id, $actor->id);

                SendSmsMessage::dispatch($message->id, $message->barangay_id);
            }

            return $messages->count();
        });
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, SmsMessage>
     */
    private function locked(array $ids): Collection
    {
        return SmsMessage::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
    }

    /**
     * @param  Collection<int, SmsMessage>  $messages
     * @param  callable(SmsMessage): bool  $allowed
     */
    private function ensureAll(Collection $messages, callable $allowed, ErrorCode $code): void
    {
        $rejected = $messages->reject($allowed);

        if ($rejected->isNotEmpty()) {
            throw BmsException::of($code, [
                'ids' => $rejected->map(fn (SmsMessage $message): string => self::ID_PREFIX.$message->id)->implode(self::ID_SEPARATOR),
            ]);
        }
    }
}
