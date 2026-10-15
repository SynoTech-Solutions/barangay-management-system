<?php

namespace App\Services\Forms;

use App\Domain\Forms\FieldValueFormatter;
use App\Domain\Forms\FormSnapshot;
use App\Domain\Forms\Ordinal;
use App\Domain\Forms\Placeholder;
use App\Domain\Forms\PlaceholderCatalog as Catalog;
use App\Domain\Money\Centavos;
use App\Domain\Residents\AddressComposer;
use App\Domain\Residents\ResidentAge;
use App\Enums\SignatoryOffice;
use App\Models\Resident;
use App\Models\Signatory;
use App\Queries\DocumentPrintContext;
use Carbon\CarbonImmutable;

final readonly class FormTokenResolver
{
    private const string MONTH_FORMAT = 'F';

    private const string YEAR_FORMAT = 'Y';

    private const string NAME_SEPARATOR = ' ';

    public function __construct(private DocumentPrintContext $context) {}

    /**
     * @return array<string, string>
     */
    public function snapshot(Resident $resident, CarbonImmutable $on): array
    {
        $resident->loadMissing(['purok:id,name', 'birthplace:id,name', 'nationality:id,label', 'religion:id,label', 'title:id,label']);
        $header = $this->context->header();
        $signatories = Signatory::forOffices(SignatoryOffice::PunongBarangay, SignatoryOffice::Secretary, SignatoryOffice::Treasurer);
        $captain = $signatories->get(SignatoryOffice::PunongBarangay->value);
        $secretary = $signatories->get(SignatoryOffice::Secretary->value);
        $treasurer = $signatories->get(SignatoryOffice::Treasurer->value);
        $age = ResidentAge::years($resident->birthdate, $on);

        $residentTokens = [
            'full_name' => implode(self::NAME_SEPARATOR, array_filter([$resident->first_name, $resident->middle_name, $resident->last_name, $resident->name_extension])),
            'first_name' => $resident->first_name,
            'middle_name' => $resident->middle_name ?? '',
            'last_name' => $resident->last_name,
            'name_extension' => $resident->name_extension ?? '',
            'title' => $resident->title->label ?? '',
            'resident_number' => $resident->resident_number,
            'address' => $this->context->residentAddress($resident),
            'street_address' => $resident->street_address ?? '',
            'purok' => $resident->purok->name ?? '',
            'age' => $age === null ? '' : (string) $age,
            'birthdate' => $resident->birthdate?->format(FieldValueFormatter::DATE_INPUT_FORMAT) ?? '',
            'birthdate_long' => $resident->birthdate?->format(FieldValueFormatter::DATE_DISPLAY_FORMAT) ?? '',
            'birthplace' => $resident->birthplace->name ?? '',
            'gender' => $resident->gender === null ? '' : (string) __($resident->gender->labelKey()),
            'civil_status' => $resident->civil_status === null ? '' : (string) __($resident->civil_status->labelKey()),
            'nationality' => $resident->nationality->label ?? '',
            'religion' => $resident->religion->label ?? '',
        ];

        $barangay = [
            'name' => $header['barangay'],
            'municipality' => $header['municipality'],
            'province' => $header['province'] ?? '',
            'locality' => (string) __('forms.locality', ['place' => AddressComposer::compose([$header['barangay'], $header['municipality'], $header['province']])]),
            'telephone' => $header['telephone'] ?? '',
        ];

        $signatory = [
            'punong_barangay' => $captain->name ?? '',
            'punong_barangay_title' => $captain->title ?? '',
            'secretary' => $secretary->name ?? '',
            'secretary_title' => $secretary->title ?? '',
            'treasurer' => $treasurer->name ?? '',
            'treasurer_title' => $treasurer->title ?? '',
        ];

        return [
            ...$this->prefix(Catalog::RESIDENT, $residentTokens),
            ...$this->prefix(Catalog::BARANGAY, $barangay),
            ...$this->prefix(Catalog::SIGNATORY, $signatory),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function document(FormSnapshot $snapshot, string $number, ?string $orNumber, string $amount, CarbonImmutable $issuedAt): array
    {
        $place = AddressComposer::compose([
            $snapshot->tokens[Placeholder::token(Catalog::BARANGAY, 'name')] ?? null,
            $snapshot->tokens[Placeholder::token(Catalog::BARANGAY, 'municipality')] ?? null,
            $snapshot->tokens[Placeholder::token(Catalog::BARANGAY, 'province')] ?? null,
        ]);

        return $this->prefix(Catalog::DOCUMENT, [
            'number' => $number,
            'or_number' => $orNumber ?? '',
            'amount_paid' => Centavos::grouped(Centavos::fromDecimal($amount)),
            'date' => $issuedAt->format(FieldValueFormatter::DATE_DISPLAY_FORMAT),
            'given_day' => Ordinal::of($issuedAt->day),
            'given_month' => $issuedAt->format(self::MONTH_FORMAT),
            'given_year' => $issuedAt->format(self::YEAR_FORMAT),
            'given_date' => (string) __('forms.given_date', [
                'day' => Ordinal::of($issuedAt->day),
                'month' => $issuedAt->format(self::MONTH_FORMAT),
                'year' => $issuedAt->format(self::YEAR_FORMAT),
                'place' => $place,
            ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function samples(CarbonImmutable $on): array
    {
        $values = [];
        foreach (Catalog::groups() as $namespace => $keys) {
            foreach ($keys as $key) {
                $values[Placeholder::token($namespace, $key)] = (string) __("forms.samples.{$namespace}.{$key}");
            }
        }

        $snapshot = new FormSnapshot([], $values);

        return [...$values, ...$this->document($snapshot, (string) __('forms.samples.document.number'), (string) __('forms.samples.document.or_number'), (string) __('forms.samples.amount'), $on)];
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function prefix(string $namespace, array $values): array
    {
        $prefixed = [];
        foreach ($values as $key => $value) {
            $prefixed[Placeholder::token($namespace, $key)] = $value;
        }

        return $prefixed;
    }
}
