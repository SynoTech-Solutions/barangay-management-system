<?php

namespace App\Services\Holds;

use App\Enums\BlotterPartyRole;
use App\Enums\BlotterStatus;
use App\Enums\FeatureFlag;
use App\Models\BlotterParty;
use App\Models\Resident;
use App\Services\Features\FeatureFlags;
use Illuminate\Database\Eloquent\Builder;

final readonly class BlotterHoldChecker implements ResidentHoldChecker
{
    public function __construct(private FeatureFlags $features) {}

    public function isOnHold(Resident $resident): bool
    {
        return $this->heldAmong([$resident->id]) !== [];
    }

    public function heldAmong(array $residentIds): array
    {
        if ($residentIds === [] || ! $this->features->enabled(FeatureFlag::UnresolvedComplaintHold)) {
            return [];
        }

        return array_values(array_map('intval', BlotterParty::query()
            ->where('role', BlotterPartyRole::Respondent->value)
            ->whereIn('resident_id', $residentIds)
            ->whereHas('blotter', fn (Builder $blotter) => $blotter->where('status', BlotterStatus::Open->value))
            ->distinct()
            ->pluck('resident_id')
            ->all()));
    }
}
