<?php

namespace App\Services\Users;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;

final readonly class SelfService
{
    public function __construct(private AuditTrail $audit) {}

    /**
     * @param  array{name: string, email: string|null}  $attributes
     */
    public function updateProfile(User $user, array $attributes): void
    {
        DB::transaction(function () use ($user, $attributes): void {
            $before = $user->only(['name', 'email']);
            $user->fill($attributes)->save();

            $this->audit->record(AuditAction::Updated, $user, $before, $user->only(['name', 'email']), $user->barangay_id, $user->id);
        });
    }

    public function changePassword(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $user->password = $password;
            $user->save();

            $this->audit->record(AuditAction::PasswordChanged, $user, barangayId: $user->barangay_id, actorId: $user->id);
        });
    }
}
