<?php

namespace App\Services\Cedula;

use App\Domain\Cedula\CommunityTaxBreakdown;
use App\Domain\Cedula\CommunityTaxCalculator;
use App\Domain\Cedula\IndividualTaxInput;
use App\Domain\Money\Centavos;
use App\Domain\Residents\AddressComposer;
use App\Enums\DocumentKind;
use App\Enums\FeatureFlag;
use App\Enums\SignatoryOffice;
use App\Models\Cedula;
use App\Models\Resident;
use App\Models\Signatory;
use App\Models\User;
use App\Services\Documents\DocumentLifecycle;
use App\Services\Documents\ResidentIssuanceGuard;
use App\Services\Features\FeatureFlags;
use App\Services\Numbering\DocumentNumberAllocator;
use App\Tenancy\CurrentBarangay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class CedulaService
{
    private const array AUDITED_ATTRIBUTES = [
        'resident_id', 'cedula_number', 'form_number', 'tax_year', ...Cedula::INPUT_ATTRIBUTES,
        'additional_tax', 'interest', 'amount', 'certified_by_name',
    ];

    public function __construct(
        private DocumentLifecycle $lifecycle,
        private DocumentNumberAllocator $numbers,
        private ResidentIssuanceGuard $guard,
        private FeatureFlags $features,
        private CurrentBarangay $currentBarangay,
    ) {}

    /**
     * @param  array{is_exempt: bool, business_gross_receipts: string, salaries: string, real_property_income: string}  $inputs
     */
    public function quote(array $inputs, CarbonImmutable $paidOn): CommunityTaxBreakdown
    {
        return CommunityTaxCalculator::individual(
            new IndividualTaxInput(
                taxYear: $paidOn->year,
                exempt: $inputs['is_exempt'],
                businessGrossReceipts: Centavos::fromDecimal($inputs['business_gross_receipts']),
                salaries: Centavos::fromDecimal($inputs['salaries']),
                realPropertyIncome: Centavos::fromDecimal($inputs['real_property_income']),
            ),
            $paidOn,
            $this->features->enabled(FeatureFlag::CedulaInterest),
        );
    }

    /**
     * @param  array{is_exempt: bool, business_gross_receipts: string, salaries: string, real_property_income: string}  $inputs
     */
    public function issue(int $residentId, array $inputs, ?string $formNumber, User $actor): Cedula
    {
        return DB::transaction(function () use ($residentId, $inputs, $formNumber, $actor): Cedula {
            $resident = Resident::query()->lockForUpdate()->findOrFail($residentId);
            $this->guard->ensureIssuable($resident, applyComplaintHold: false, requirePhoto: false);

            $now = CarbonImmutable::now();
            $breakdown = $this->quote($inputs, $now);
            $barangay = $this->currentBarangay->require()->loadMissing('municipality.province');

            $cedula = new Cedula;
            $cedula->fill([...$inputs, 'form_number' => $formNumber]);
            $cedula->resident_id = $resident->id;
            $cedula->cedula_number = $this->numbers->next(DocumentKind::Cedula, $now->year);
            $cedula->tax_year = $breakdown->input->taxYear;
            $cedula->place_of_issue = AddressComposer::compose([$barangay->name, $barangay->municipality->name, $barangay->municipality->province?->name]);
            $cedula->basic_tax = Centavos::toDecimal($breakdown->basic);
            $cedula->business_tax = Centavos::toDecimal($breakdown->business);
            $cedula->salary_tax = Centavos::toDecimal($breakdown->salary);
            $cedula->real_property_tax = Centavos::toDecimal($breakdown->realProperty);
            $cedula->additional_tax = Centavos::toDecimal($breakdown->additional);
            $cedula->interest = Centavos::toDecimal($breakdown->interest);
            $cedula->amount = Centavos::toDecimal($breakdown->total);
            $cedula->breakdown = $breakdown->toArray();
            $cedula->snapshotSignatories(null, Signatory::forOffices(SignatoryOffice::Treasurer)->get(SignatoryOffice::Treasurer->value));

            return $this->lifecycle->issue($cedula, $actor, $now, self::AUDITED_ATTRIBUTES);
        });
    }

    /**
     * @param  list<int>  $ids
     */
    public function release(array $ids, User $actor): int
    {
        return $this->lifecycle->release(Cedula::class, $ids, $actor);
    }

    /**
     * @param  list<int>  $ids
     */
    public function cancel(array $ids, string $reason, User $actor): int
    {
        return $this->lifecycle->cancel(Cedula::class, $ids, $reason, $actor);
    }

    public function recordPrint(Cedula $cedula, User $actor): Cedula
    {
        return $this->lifecycle->recordPrint($cedula, $actor);
    }
}
