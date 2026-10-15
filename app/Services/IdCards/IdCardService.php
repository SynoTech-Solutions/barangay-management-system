<?php

namespace App\Services\IdCards;

use App\Enums\DocumentKind;
use App\Enums\SignatoryOffice;
use App\Models\IdCard;
use App\Models\Resident;
use App\Models\Signatory;
use App\Models\User;
use App\Services\Documents\DocumentLifecycle;
use App\Services\Documents\ResidentIssuanceGuard;
use App\Services\Numbering\DocumentNumberAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class IdCardService
{
    private const array AUDITED_ATTRIBUTES = [
        'resident_id', 'card_number', ...IdCard::DETAIL_ATTRIBUTES,
        'verified_by_name', 'certified_by_name',
    ];

    public function __construct(
        private DocumentLifecycle $lifecycle,
        private DocumentNumberAllocator $numbers,
        private ResidentIssuanceGuard $guard,
    ) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public function issue(int $residentId, array $details, User $actor): IdCard
    {
        return DB::transaction(function () use ($residentId, $details, $actor): IdCard {
            $resident = Resident::query()->lockForUpdate()->findOrFail($residentId);
            $this->guard->ensureIssuable($resident, applyComplaintHold: true);

            $now = CarbonImmutable::now();
            $signatories = Signatory::forOffices(SignatoryOffice::Secretary, SignatoryOffice::PunongBarangay);

            $card = new IdCard;
            $card->fill($details);
            $card->resident_id = $resident->id;
            $card->card_number = $this->numbers->next(DocumentKind::IdCard, $now->year);
            $card->snapshotSignatories($signatories->get(SignatoryOffice::Secretary->value), $signatories->get(SignatoryOffice::PunongBarangay->value));

            return $this->lifecycle->issue($card, $actor, $now, self::AUDITED_ATTRIBUTES);
        });
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function update(IdCard $card, array $details, User $actor): IdCard
    {
        return $this->lifecycle->update($card, $actor, self::AUDITED_ATTRIBUTES, fn (IdCard $locked) => $locked->fill($details));
    }

    /**
     * @param  list<int>  $cardIds
     */
    public function release(array $cardIds, User $actor): int
    {
        return $this->lifecycle->release(IdCard::class, $cardIds, $actor);
    }

    /**
     * @param  list<int>  $cardIds
     */
    public function cancel(array $cardIds, string $reason, User $actor): int
    {
        return $this->lifecycle->cancel(IdCard::class, $cardIds, $reason, $actor);
    }

    public function recordPrint(IdCard $card, User $actor): IdCard
    {
        return $this->lifecycle->recordPrint($card, $actor);
    }
}
