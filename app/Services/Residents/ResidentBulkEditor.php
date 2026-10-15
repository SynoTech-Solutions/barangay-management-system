<?php

namespace App\Services\Residents;

use App\Enums\AuditAction;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;

final readonly class ResidentBulkEditor
{
    public function __construct(private AuditTrail $audit) {}

    /**
     * @param  list<int>  $residentIds
     */
    public function changePurok(array $residentIds, int $purokId, User $actor): int
    {
        return DB::transaction(function () use ($residentIds, $purokId, $actor): int {
            $purok = Purok::query()->findOrFail($purokId);
            $residents = Resident::query()
                ->whereKey($residentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $changed = 0;

            foreach ($residents as $resident) {
                if ($resident->purok_id === $purok->id) {
                    continue;
                }

                $before = ['purok_id' => $resident->purok_id];
                $resident->purok_id = $purok->id;
                $resident->updated_by = $actor->id;
                $resident->save();

                $this->audit->record(AuditAction::ResidentBulkPurokChanged, $resident, $before, ['purok_id' => $purok->id], $resident->barangay_id, $actor->id);
                $changed++;
            }

            return $changed;
        });
    }
}
