<?php

namespace App\Services\Barangays;

use App\Enums\AuditAction;
use App\Enums\BarangayStatus;
use App\Enums\LogEvent;
use App\Enums\SystemRole;
use App\Models\Barangay;
use App\Models\BarangayProfile;
use App\Models\User;
use App\Services\Access\RoleSynchronizer;
use App\Services\Audit\AuditTrail;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Users\UserAdministration;
use App\Tenancy\CurrentBarangay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class BarangayOnboarding
{
    public function __construct(
        private AuditTrail $audit,
        private RoleSynchronizer $roles,
        private UserAdministration $users,
        private CurrentBarangay $currentBarangay,
        private ChartOfAccounts $chart,
    ) {}

    /**
     * @param  array{municipality_id: int, psgc_code: string, name: string}  $attributes
     * @param  array{name: string, username: string, email?: string|null, password: string}|null  $firstAdmin
     */
    public function onboard(array $attributes, ?array $firstAdmin, User $actor): Barangay
    {
        $barangay = DB::transaction(function () use ($attributes, $firstAdmin, $actor): Barangay {
            $barangay = Barangay::query()->create([...$attributes, 'status' => BarangayStatus::Active]);

            $this->currentBarangay->runAs($barangay, fn () => BarangayProfile::query()->create([]));
            $this->chart->installStandard($barangay);

            $this->audit->record(AuditAction::Created, $barangay, after: $barangay->only(['psgc_code', 'name', 'municipality_id']), barangayId: $barangay->id, actorId: $actor->id);

            if ($firstAdmin !== null) {
                $this->users->create(
                    $barangay,
                    $firstAdmin,
                    [$this->roles->systemRole(SystemRole::BarangayAdmin)->id],
                    $actor,
                );
            }

            return $barangay;
        });

        Log::info(LogEvent::BarangayOnboarded->value, ['barangay_id' => $barangay->id, 'psgc_code' => $barangay->psgc_code]);

        return $barangay;
    }

    public function setStatus(Barangay $barangay, BarangayStatus $status, User $actor): void
    {
        DB::transaction(function () use ($barangay, $status, $actor): void {
            $before = ['status' => $barangay->status->value];
            $barangay->status = $status;
            $barangay->save();

            $this->audit->record(AuditAction::Updated, $barangay, $before, ['status' => $status->value], $barangay->id, $actor->id);
        });
    }
}
