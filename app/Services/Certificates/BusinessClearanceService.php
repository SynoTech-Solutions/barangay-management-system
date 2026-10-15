<?php

namespace App\Services\Certificates;

use App\Domain\Residents\AddressComposer;
use App\Enums\DocumentKind;
use App\Enums\ErrorCode;
use App\Enums\SignatoryOffice;
use App\Exceptions\BmsException;
use App\Models\Business;
use App\Models\BusinessClearance;
use App\Models\Signatory;
use App\Models\User;
use App\Services\Documents\DocumentLifecycle;
use App\Services\Numbering\DocumentNumberAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class BusinessClearanceService
{
    private const array AUDITED_ATTRIBUTES = [
        'business_id', 'clearance_number', 'control_number', ...BusinessClearance::DETAIL_ATTRIBUTES,
        'expires_on', 'certified_by_name',
    ];

    public function __construct(
        private DocumentLifecycle $lifecycle,
        private DocumentNumberAllocator $numbers,
        private ClearanceSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public function issue(int $businessId, array $details, User $actor): BusinessClearance
    {
        return DB::transaction(function () use ($businessId, $details, $actor): BusinessClearance {
            $business = Business::query()->with(['owner:id,full_name', 'purok:id,name'])->lockForUpdate()->findOrFail($businessId);

            if (! $business->isActive()) {
                throw BmsException::of(ErrorCode::BusinessInactive, ['name' => $business->name]);
            }

            $now = CarbonImmutable::now();
            $signatories = Signatory::forOffices(SignatoryOffice::Secretary, SignatoryOffice::PunongBarangay);

            $clearance = new BusinessClearance;
            $clearance->fill($this->settings->enabledDetails($details));
            $clearance->business_id = $business->id;
            $clearance->business_name = $business->name;
            $address = AddressComposer::compose([$business->street_address, $business->purok?->name]);
            $clearance->business_address = $address === '' ? null : $address;
            $clearance->owner_resident_id = $business->owner_resident_id;
            $clearance->owner_name = $business->ownerDisplayName();
            $clearance->clearance_number = $this->numbers->next(DocumentKind::BusinessClearance, $now->year);
            $clearance->control_number = $this->numbers->next(DocumentKind::BusinessClearanceControl, $now->year);
            $clearance->expires_on = $this->settings->expiresOn($now);
            $clearance->snapshotSignatories($signatories->get(SignatoryOffice::Secretary->value), $signatories->get(SignatoryOffice::PunongBarangay->value));

            return $this->lifecycle->issue($clearance, $actor, $now, self::AUDITED_ATTRIBUTES);
        });
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function update(BusinessClearance $clearance, array $details, User $actor): BusinessClearance
    {
        $details = $this->settings->enabledDetails($details);

        return $this->lifecycle->update($clearance, $actor, self::AUDITED_ATTRIBUTES, fn (BusinessClearance $locked) => $locked->fill($details));
    }

    /**
     * @param  list<int>  $ids
     */
    public function release(array $ids, User $actor): int
    {
        return $this->lifecycle->release(BusinessClearance::class, $ids, $actor);
    }

    /**
     * @param  list<int>  $ids
     */
    public function cancel(array $ids, string $reason, User $actor): int
    {
        return $this->lifecycle->cancel(BusinessClearance::class, $ids, $reason, $actor);
    }

    public function recordPrint(BusinessClearance $clearance, User $actor): BusinessClearance
    {
        return $this->lifecycle->recordPrint($clearance, $actor);
    }
}
