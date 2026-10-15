<?php

namespace App\Services\Documents;

use App\Enums\ErrorCode;
use App\Enums\FeatureFlag;
use App\Exceptions\BmsException;
use App\Models\Resident;
use App\Services\Features\FeatureFlags;
use App\Services\Holds\ResidentHoldChecker;

final readonly class ResidentIssuanceGuard
{
    public function __construct(
        private FeatureFlags $features,
        private ResidentHoldChecker $holds,
    ) {}

    public function ensureIssuable(Resident $resident, bool $applyComplaintHold, bool $requirePhoto = true): void
    {
        if ($resident->is_deceased) {
            throw BmsException::of(ErrorCode::ResidentDeceased, ['name' => $resident->full_name]);
        }

        if ($requirePhoto && $resident->photo_path === null && $this->features->enabled(FeatureFlag::IssuanceRequiresPhoto)) {
            throw BmsException::of(ErrorCode::ResidentPhotoRequired, ['name' => $resident->full_name]);
        }

        if ($applyComplaintHold && $this->holds->isOnHold($resident)) {
            throw BmsException::of(ErrorCode::ResidentOnHold, ['name' => $resident->full_name]);
        }
    }
}
