<?php

namespace App\Queries;

use App\Domain\Residents\IdCardValidity;
use App\Enums\FeatureFlag;
use App\Enums\SignatoryOffice;
use App\Models\IdCard;
use App\Models\Resident;
use App\Models\Signatory;
use App\Services\Features\FeatureFlags;
use App\Support\Config\BmsSettings;

final readonly class IdCardPrintQuery
{
    public function __construct(
        private DocumentPrintContext $context,
        private FeatureFlags $features,
        private BmsSettings $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function data(IdCard $card): array
    {
        $resident = Resident::query()->withTrashed()->findOrFail($card->resident_id);
        $signatories = Signatory::forOffices(SignatoryOffice::PunongBarangay, SignatoryOffice::Mayor);

        return [
            'card' => [
                'id' => $card->id,
                'card_number' => $card->card_number,
                'issued_on' => $card->issued_at->format(DocumentPrintContext::DATE_FORMAT),
                'valid_until' => $this->features->enabled(FeatureFlag::IdCardValidity)
                    ? IdCardValidity::until($card->issued_at, $this->settings->idCardValidityMonths)->format(DocumentPrintContext::DATE_FORMAT)
                    : null,
                'print_count' => $card->print_count,
            ],
            'resident' => [
                ...$this->context->residentFacts($resident),
                'qr' => $this->context->qr($resident->resident_number),
            ],
            'header' => $this->context->header(),
            'captain' => $this->context->signatory($signatories->get(SignatoryOffice::PunongBarangay->value)),
            'mayor' => $this->context->signatory($signatories->get(SignatoryOffice::Mayor->value)),
            'layout' => [
                'pvc' => $this->features->enabled(FeatureFlag::PvcIdPrinting),
            ],
        ];
    }
}
