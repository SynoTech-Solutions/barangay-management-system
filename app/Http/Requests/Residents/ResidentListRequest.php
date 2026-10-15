<?php

namespace App\Http\Requests\Residents;

use App\Domain\Residents\NameQuery;
use App\Enums\CivilStatus;
use App\Enums\Gender;
use App\Enums\Permission;
use App\Enums\ResidentFlag;
use App\Enums\ResidentSort;
use App\Enums\SortDirection;
use App\Models\Resident;
use App\Queries\Filters\ResidentListFilters;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResidentListRequest extends FormRequest
{
    private const int SEARCH_MAX = 100;

    private const string VOTER_YES = 'yes';

    private const string VOTER_NO = 'no';

    private const array BOOLEAN_VALUES = ['0', '1', 'true', 'false'];

    private const int AGE_MAX = 150;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.self::SEARCH_MAX],
            'purok_id' => ['nullable', 'integer'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'civil_status' => ['nullable', Rule::enum(CivilStatus::class)],
            'voter' => ['nullable', Rule::in([self::VOTER_YES, self::VOTER_NO])],
            'flags' => ['nullable', 'array'],
            'flags.*' => [Rule::enum(ResidentFlag::class)],
            'sector_id' => ['nullable', 'integer'],
            'include_incomplete' => ['nullable', Rule::in(self::BOOLEAN_VALUES)],
            'removed' => ['nullable', Rule::in(self::BOOLEAN_VALUES)],
            'sort' => ['nullable', Rule::enum(ResidentSort::class)],
            'direction' => ['nullable', Rule::enum(SortDirection::class)],
            'per_page' => ['nullable', 'integer'],
            'age_from' => ['nullable', 'integer', 'min:0', 'max:'.self::AGE_MAX],
            'age_to' => ['nullable', 'integer', 'min:0', 'max:'.self::AGE_MAX],
            'attribute' => ['nullable', Rule::in(array_keys(Resident::LOOKUP_ATTRIBUTES))],
            'attribute_values' => ['nullable', 'array'],
            'attribute_values.*' => ['integer'],
        ];
    }

    public function filters(CarbonImmutable $today, bool $sectorsEnabled): ResidentListFilters
    {
        $voter = $this->string('voter')->toString();
        $attribute = array_key_exists($this->string('attribute')->toString(), Resident::LOOKUP_ATTRIBUTES) ? $this->string('attribute')->toString() : null;
        $user = $this->user();

        return new ResidentListFilters(
            today: $today,
            name: NameQuery::parse($this->string('search')->toString()),
            purokId: $this->filled('purok_id') ? $this->integer('purok_id') : null,
            gender: Gender::tryFrom($this->string('gender')->toString()),
            civilStatus: CivilStatus::tryFrom($this->string('civil_status')->toString()),
            registeredVoter: match ($voter) {
                self::VOTER_YES => true,
                self::VOTER_NO => false,
                default => null,
            },
            flags: array_values(array_filter(array_map(
                fn (mixed $flag): ?ResidentFlag => is_string($flag) ? ResidentFlag::tryFrom($flag) : null,
                (array) $this->input('flags', []),
            ))),
            sectorId: $sectorsEnabled && $this->filled('sector_id') ? $this->integer('sector_id') : null,
            includeIncomplete: $this->boolean('include_incomplete'),
            removed: $this->boolean('removed') && $user !== null && $user->can(Permission::ResidentsDelete->value),
            sort: ResidentSort::tryFrom($this->string('sort')->toString()) ?? ResidentSort::Name,
            direction: SortDirection::tryFrom($this->string('direction')->toString()) ?? SortDirection::Ascending,
            ageFrom: $this->filled('age_from') ? $this->integer('age_from') : null,
            ageTo: $this->filled('age_to') ? $this->integer('age_to') : null,
            attribute: $attribute,
            attributeValues: $attribute === null ? [] : array_values(array_map('intval', (array) $this->input('attribute_values', []))),
        );
    }

    public function perPage(): ?int
    {
        return $this->filled('per_page') ? $this->integer('per_page') : null;
    }
}
