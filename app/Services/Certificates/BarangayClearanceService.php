<?php

namespace App\Services\Certificates;

use App\Enums\DocumentKind;
use App\Enums\FeatureFlag;
use App\Enums\SignatoryOffice;
use App\Models\BarangayClearance;
use App\Models\Resident;
use App\Models\Signatory;
use App\Models\User;
use App\Services\Documents\DocumentLifecycle;
use App\Services\Documents\ResidentIssuanceGuard;
use App\Services\Features\FeatureFlags;
use App\Services\Numbering\DocumentNumberAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class BarangayClearanceService
{
    private const array AUDITED_ATTRIBUTES = [
        'resident_id', 'clearance_number', 'control_number', ...BarangayClearance::DETAIL_ATTRIBUTES,
        'expires_on', 'verified_by_name', 'certified_by_name',
    ];

    public function __construct(
        private DocumentLifecycle $lifecycle,
        private DocumentNumberAllocator $numbers,
        private ResidentIssuanceGuard $guard,
        private ClearanceSettings $settings,
        private FeatureFlags $features,
    ) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public function issue(int $residentId, array $details, User $actor): BarangayClearance
    {
        return DB::transaction(function () use ($residentId, $details, $actor): BarangayClearance {
            $resident = Resident::query()->lockForUpdate()->findOrFail($residentId);
            $this->guard->ensureIssuable($resident, applyComplaintHold: true);

            $now = CarbonImmutable::now();
            $signatories = Signatory::forOffices(SignatoryOffice::Secretary, SignatoryOffice::PunongBarangay);

            $clearance = new BarangayClearance;
            $clearance->fill($this->settings->enabledDetails($details));
            $clearance->resident_id = $resident->id;
            $clearance->clearance_number = $this->numbers->next(DocumentKind::BarangayClearance, $now->year);
            $clearance->control_number = $this->numbers->next(DocumentKind::BarangayClearanceControl, $now->year);
            $clearance->expires_on = $this->settings->expiresOn($now);
            $clearance->snapshotSignatories(
                $this->features->enabled(FeatureFlag::SecretaryApprovalBlock) ? $signatories->get(SignatoryOffice::Secretary->value) : null,
                $signatories->get(SignatoryOffice::PunongBarangay->value),
            );

            return $this->lifecycle->issue($clearance, $actor, $now, self::AUDITED_ATTRIBUTES);
        });
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function update(BarangayClearance $clearance, array $details, User $actor): BarangayClearance
    {
        $details = $this->settings->enabledDetails($details);

        return $this->lifecycle->update($clearance, $actor, self::AUDITED_ATTRIBUTES, fn (BarangayClearance $locked) => $locked->fill($details));
    }

    /**
     * @param  list<int>  $ids
     */
    public function release(array $ids, User $actor): int
    {
        return $this->lifecycle->release(BarangayClearance::class, $ids, $actor);
    }

    /**
     * @param  list<int>  $ids
     */
    public function cancel(array $ids, string $reason, User $actor): int
    {
        return $this->lifecycle->cancel(BarangayClearance::class, $ids, $reason, $actor);
    }

    public function recordPrint(BarangayClearance $clearance, User $actor): BarangayClearance
    {
        return $this->lifecycle->recordPrint($clearance, $actor);
    }
}
