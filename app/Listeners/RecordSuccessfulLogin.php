<?php

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Enums\LogEvent;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

final readonly class RecordSuccessfulLogin
{
    public function __construct(private AuditTrail $audit) {}

    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->audit->record(AuditAction::LoggedIn, $user, barangayId: $user->barangay_id, actorId: $user->id);

        Log::info(LogEvent::LoginSucceeded->value, ['user_id' => $user->id, 'barangay_id' => $user->barangay_id]);
    }
}
