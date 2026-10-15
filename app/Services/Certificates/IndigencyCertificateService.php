<?php

namespace App\Services\Certificates;

use App\Enums\DocumentKind;
use App\Enums\SignatoryOffice;
use App\Models\IndigencyCertificate;
use App\Models\Resident;
use App\Models\Signatory;
use App\Models\User;
use App\Services\Documents\DocumentLifecycle;
use App\Services\Documents\ResidentIssuanceGuard;
use App\Services\Numbering\DocumentNumberAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class IndigencyCertificateService
{
    private const array AUDITED_ATTRIBUTES = ['resident_id', 'certificate_number', ...IndigencyCertificate::DETAIL_ATTRIBUTES, 'certified_by_name'];

    public function __construct(
        private DocumentLifecycle $lifecycle,
        private DocumentNumberAllocator $numbers,
        private ResidentIssuanceGuard $guard,
    ) {}

    public function issue(int $residentId, int $purposeId, User $actor): IndigencyCertificate
    {
        return DB::transaction(function () use ($residentId, $purposeId, $actor): IndigencyCertificate {
            $resident = Resident::query()->lockForUpdate()->findOrFail($residentId);
            $this->guard->ensureIssuable($resident, applyComplaintHold: false);

            $now = CarbonImmutable::now();
            $certificate = new IndigencyCertificate;
            $certificate->purpose_id = $purposeId;
            $certificate->resident_id = $resident->id;
            $certificate->certificate_number = $this->numbers->next(DocumentKind::IndigencyCertificate, $now->year);
            $certificate->snapshotSignatories(null, Signatory::forOffices(SignatoryOffice::PunongBarangay)->get(SignatoryOffice::PunongBarangay->value));

            return $this->lifecycle->issue($certificate, $actor, $now, self::AUDITED_ATTRIBUTES);
        });
    }

    public function update(IndigencyCertificate $certificate, int $purposeId, User $actor): IndigencyCertificate
    {
        return $this->lifecycle->update($certificate, $actor, self::AUDITED_ATTRIBUTES, fn (IndigencyCertificate $locked) => $locked->purpose_id = $purposeId);
    }

    /**
     * @param  list<int>  $ids
     */
    public function release(array $ids, User $actor): int
    {
        return $this->lifecycle->release(IndigencyCertificate::class, $ids, $actor);
    }

    /**
     * @param  list<int>  $ids
     */
    public function cancel(array $ids, string $reason, User $actor): int
    {
        return $this->lifecycle->cancel(IndigencyCertificate::class, $ids, $reason, $actor);
    }

    public function recordPrint(IndigencyCertificate $certificate, User $actor): IndigencyCertificate
    {
        return $this->lifecycle->recordPrint($certificate, $actor);
    }
}
