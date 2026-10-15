<?php

namespace App\Services\Residents;

use App\Enums\AuditAction;
use App\Models\Resident;
use App\Models\User;
use App\Services\Audit\AuditTrail;

final readonly class SpouseLinker
{
    public function __construct(private AuditTrail $audit) {}

    public function link(Resident $resident, ?int $spouseId, User $actor): void
    {
        $previousId = $resident->spouse_id;

        if ($previousId !== null && $previousId !== $spouseId) {
            $this->clearBackLink($previousId, $resident->id, $actor);
        }

        if ($spouseId !== null) {
            $spouse = Resident::query()->lockForUpdate()->findOrFail($spouseId);

            if ($spouse->spouse_id !== null && $spouse->spouse_id !== $resident->id) {
                $this->clearBackLink($spouse->spouse_id, $spouse->id, $actor);
            }

            $this->setSpouse($spouse, $resident->id, $actor);
        }

        $resident->spouse_id = $spouseId;
        $resident->save();
    }

    private function clearBackLink(int $partnerId, int $formerSpouseId, User $actor): void
    {
        $partner = Resident::query()->lockForUpdate()->find($partnerId);

        if ($partner !== null && $partner->spouse_id === $formerSpouseId) {
            $this->setSpouse($partner, null, $actor);
        }
    }

    private function setSpouse(Resident $resident, ?int $spouseId, User $actor): void
    {
        if ($resident->spouse_id === $spouseId) {
            return;
        }

        $before = ['spouse_id' => $resident->spouse_id];
        $resident->spouse_id = $spouseId;
        $resident->updated_by = $actor->id;
        $resident->save();

        $this->audit->record(AuditAction::ResidentUpdated, $resident, $before, ['spouse_id' => $spouseId], $resident->barangay_id, $actor->id);
    }
}
