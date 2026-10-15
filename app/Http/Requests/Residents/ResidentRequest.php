<?php

namespace App\Http\Requests\Residents;

use App\Domain\Residents\NameNormalizer;
use App\Domain\Residents\PhilippineMobileNumber;
use App\Enums\CivilStatus;
use App\Enums\Gender;
use App\Enums\LookupType;
use App\Http\Requests\Residents\Concerns\WarnsAboutDuplicates;
use App\Models\Resident;
use App\Rules\ActiveResident;
use App\Rules\LookupOption;
use App\Rules\PhilippineMobile;
use App\Rules\PurokOption;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ResidentRequest extends FormRequest
{
    use WarnsAboutDuplicates;

    public const int NAME_MAX = 100;

    public const int EXTENSION_MAX = 10;

    private const int TEXT_MAX = 255;

    private const int ADDRESS_MAX = 1000;

    private const int GOVERNMENT_ID_MAX = 32;

    private const int PRECINCT_MAX = 16;

    private const int LANDLINE_MAX = 32;

    private const int MEASUREMENT_MAX = 999;

    private const array NAME_FIELDS = ['last_name', 'first_name', 'middle_name', 'name_extension', 'nickname'];

    private const array TEXT_FIELDS = [
        'national_id', 'drivers_license', 'tin', 'sss_gsis', 'pagibig', 'philhealth', 'voter_id', 'precinct_number',
        'street_address', 'alternate_address', 'landline_number', 'email', 'facebook_url', 'cause_of_death',
    ];

    private const array DATE_FIELDS = ['birthdate', 'residing_since', 'deceased_on'];

    private const array NUMBER_FIELDS = ['height_cm', 'weight_kg'];

    private const array BOOLEAN_FIELDS = [
        'is_registered_voter', 'is_living_with_parents', 'is_senior_citizen_member', 'is_pwd', 'is_single_parent',
        'is_indigent', 'is_permanent_resident', 'is_deceased',
    ];

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach ([...self::NAME_FIELDS, ...self::TEXT_FIELDS] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = NameNormalizer::normalize($this->input($field));
            }
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->currentResident();
        $governmentId = ['nullable', 'string', 'max:'.self::GOVERNMENT_ID_MAX];

        $rules = [
            'last_name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'first_name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'middle_name' => ['nullable', 'string', 'max:'.self::NAME_MAX],
            'name_extension' => ['nullable', 'string', 'max:'.self::EXTENSION_MAX],
            'nickname' => ['nullable', 'string', 'max:'.self::NAME_MAX],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'birthplace_municipality_id' => ['nullable', 'integer', Rule::exists('municipalities', 'id')],
            'gender' => ['required', Rule::enum(Gender::class)],
            'civil_status' => ['nullable', Rule::enum(CivilStatus::class)],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:'.self::MEASUREMENT_MAX],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:'.self::MEASUREMENT_MAX],
            'national_id' => $governmentId,
            'drivers_license' => $governmentId,
            'tin' => $governmentId,
            'sss_gsis' => $governmentId,
            'pagibig' => $governmentId,
            'philhealth' => $governmentId,
            'voter_id' => $governmentId,
            'precinct_number' => ['nullable', 'string', 'max:'.self::PRECINCT_MAX],
            'purok_id' => ['required', new PurokOption($current?->purok_id)],
            'street_address' => ['nullable', 'string', 'max:'.self::TEXT_MAX],
            'residing_since' => ['nullable', 'date', 'before_or_equal:today'],
            'alternate_address' => ['nullable', 'string', 'max:'.self::ADDRESS_MAX],
            'mobile_number' => ['nullable', 'string', new PhilippineMobile],
            'landline_number' => ['nullable', 'string', 'max:'.self::LANDLINE_MAX],
            'email' => ['nullable', 'email', 'max:'.self::TEXT_MAX],
            'facebook_url' => ['nullable', 'url:http,https', 'max:'.self::TEXT_MAX],
            'deceased_on' => ['nullable', 'date', 'before_or_equal:today'],
            'cause_of_death' => ['nullable', 'string', 'max:'.self::TEXT_MAX],
            'spouse_id' => ['nullable', new ActiveResident($current?->id)],
            'sector_ids' => ['array'],
            'sector_ids.*' => ['integer', 'distinct', new LookupOption(LookupType::Sector)],
            'confirm_duplicate' => ['boolean'],
        ];

        foreach (self::BOOLEAN_FIELDS as $field) {
            $rules[$field] = ['boolean'];
        }

        foreach (Resident::LOOKUP_ATTRIBUTES as $attribute => $type) {
            $keep = $current?->getAttribute($attribute);
            $rules[$attribute] = ['nullable', new LookupOption($type, is_int($keep) ? $keep : null)];
        }

        return $rules;
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return $this->currentResident() === null
            ? [fn (Validator $validator) => $this->warnAboutDuplicate($validator)]
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        $profile = [];

        foreach ([...self::NAME_FIELDS, ...self::TEXT_FIELDS, ...self::DATE_FIELDS, ...self::NUMBER_FIELDS] as $field) {
            $profile[$field] = $this->filled($field) ? $this->string($field)->toString() : null;
        }

        foreach (self::BOOLEAN_FIELDS as $field) {
            $profile[$field] = $this->boolean($field);
        }

        foreach ([...array_keys(Resident::LOOKUP_ATTRIBUTES), 'purok_id', 'birthplace_municipality_id'] as $field) {
            $profile[$field] = $this->filled($field) ? $this->integer($field) : null;
        }

        $profile['gender'] = Gender::from($this->string('gender')->toString());
        $profile['civil_status'] = $this->filled('civil_status') ? CivilStatus::from($this->string('civil_status')->toString()) : null;
        $profile['mobile_number'] = PhilippineMobileNumber::normalize($this->filled('mobile_number') ? $this->string('mobile_number')->toString() : null);
        $profile['email'] = $this->filled('email') ? $this->string('email')->lower()->toString() : null;

        if (! $profile['is_registered_voter']) {
            $profile['voter_id'] = null;
            $profile['precinct_number'] = null;
        }

        if ($profile['is_permanent_resident']) {
            $profile['alternate_address'] = null;
        }

        if (! $profile['is_deceased']) {
            $profile['deceased_on'] = null;
            $profile['cause_of_death'] = null;
        }

        return $profile;
    }

    public function spouseId(): ?int
    {
        return $this->filled('spouse_id') ? $this->integer('spouse_id') : null;
    }

    /**
     * @return list<int>
     */
    public function sectorIds(): array
    {
        return array_values(array_map('intval', (array) $this->input('sector_ids', [])));
    }

    private function currentResident(): ?Resident
    {
        $resident = $this->route('resident');

        return $resident instanceof Resident ? $resident : null;
    }
}
