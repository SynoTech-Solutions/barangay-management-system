<?php

namespace App\Services\Certificates;

use App\Domain\Certificates\ClearanceExpiry;
use App\Enums\ClearanceValidityMode;
use App\Enums\FeatureFlag;
use App\Models\BarangayProfile;
use App\Services\Features\FeatureFlags;
use App\Tenancy\CurrentBarangay;
use Carbon\CarbonImmutable;

final readonly class ClearanceSettings
{
    public const array CTC_ATTRIBUTES = ['ctc_number', 'ctc_place', 'ctc_date'];

    public const string DOC_STAMP_ATTRIBUTE = 'doc_stamp';

    public function __construct(
        private FeatureFlags $features,
        private CurrentBarangay $currentBarangay,
    ) {}

    public function docStampEnabled(): bool
    {
        return $this->features->enabled(FeatureFlag::ClearanceDocStamp);
    }

    public function ctcReferenceEnabled(): bool
    {
        return $this->features->enabled(FeatureFlag::ClearanceCtcReference);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    public function enabledDetails(array $details): array
    {
        $disabled = [
            ...($this->docStampEnabled() ? [] : [self::DOC_STAMP_ATTRIBUTE]),
            ...($this->ctcReferenceEnabled() ? [] : self::CTC_ATTRIBUTES),
        ];

        return array_diff_key($details, array_flip($disabled));
    }

    public function expiresOn(CarbonImmutable $issuedOn): CarbonImmutable
    {
        $profile = BarangayProfile::query()->find($this->currentBarangay->require()->id);

        return ClearanceExpiry::until(
            $issuedOn,
            $profile->clearance_validity_mode ?? ClearanceValidityMode::EndOfYear,
            $profile?->clearance_validity_months,
        );
    }
}
