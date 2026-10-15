<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Enums\LogEvent;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Log;

final readonly class RecordFailedLogin
{
    public function __construct(private AuditTrail $audit) {}

    public function handle(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        $this->audit->record(
            AuditAction::LoginFailed,
            $user,
            barangayId: $user?->barangay_id,
            actorId: $user?->id,
        );

        Log::notice(LogEvent::LoginFailed->value, ['user_id' => $user?->id]);
    }
}
