<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Enums\LogEvent;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

final readonly class RecordLogout
{
    public function __construct(private AuditTrail $audit) {}

    public function handle(Logout $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        $this->audit->record(AuditAction::LoggedOut, $user, barangayId: $user->barangay_id, actorId: $user->id);

        Log::info(LogEvent::Logout->value, ['user_id' => $user->id]);
    }
}
