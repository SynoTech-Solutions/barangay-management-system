<?php

namespace App\Services\Barangays;

use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Enums\LogEvent;
use App\Enums\Permission;
use App\Exceptions\BmsException;
use App\Models\Barangay;
use App\Models\User;
use App\Services\Access\PermissionResolver;
use App\Services\Audit\AuditTrail;
use App\Support\SessionKeys;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;

final readonly class BarangaySwitcher
{
    public function __construct(
        private PermissionResolver $permissions,
        private AuditTrail $audit,
    ) {}

    public function switch(User $user, ?Barangay $barangay, Session $session): void
    {
        if (! $user->isGlobalStaff() || ! $this->permissions->has($user, Permission::BarangaysSwitch, null)) {
            throw BmsException::of(ErrorCode::BarangayNotAccessible);
        }

        if ($barangay === null) {
            $session->forget(SessionKeys::ACTIVE_BARANGAY_ID);

            return;
        }

        $session->put(SessionKeys::ACTIVE_BARANGAY_ID, $barangay->id);

        $this->audit->record(AuditAction::BarangaySwitched, $barangay, barangayId: $barangay->id, actorId: $user->id);

        Log::info(LogEvent::BarangaySwitched->value, ['barangay_id' => $barangay->id]);
    }
}
