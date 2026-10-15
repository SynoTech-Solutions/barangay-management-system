<?php

namespace App\Queries\Filters;

use App\Domain\Residents\NameQuery;
use App\Enums\CivilStatus;
use App\Enums\Gender;
use App\Enums\ResidentFlag;
use App\Enums\ResidentSort;
use App\Enums\SortDirection;
use Carbon\CarbonImmutable;

final readonly class ResidentListFilters
{
    /**
     * @param  list<ResidentFlag>  $flags
     * @param  list<int>  $attributeValues
     */
    public function __construct(
        public CarbonImmutable $today,
        public NameQuery $name,
        public ?int $purokId = null,
        public ?Gender $gender = null,
        public ?CivilStatus $civilStatus = null,
        public ?bool $registeredVoter = null,
        public array $flags = [],
        public ?int $sectorId = null,
        public bool $includeIncomplete = false,
        public bool $removed = false,
        public ResidentSort $sort = ResidentSort::Name,
        public SortDirection $direction = SortDirection::Ascending,
        public ?int $ageFrom = null,
        public ?int $ageTo = null,
        public ?string $attribute = null,
        public array $attributeValues = [],
    ) {}

    public function has(ResidentFlag $flag): bool
    {
        return in_array($flag, $this->flags, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->name->raw,
            'purok_id' => $this->purokId,
            'gender' => $this->gender?->value,
            'civil_status' => $this->civilStatus?->value,
            'voter' => $this->registeredVoter,
            'flags' => array_map(fn (ResidentFlag $flag): string => $flag->value, $this->flags),
            'sector_id' => $this->sectorId,
            'include_incomplete' => $this->includeIncomplete,
            'removed' => $this->removed,
            'sort' => $this->sort->value,
            'direction' => $this->direction->value,
            'age_from' => $this->ageFrom,
            'age_to' => $this->ageTo,
            'attribute' => $this->attribute,
            'attribute_values' => $this->attributeValues,
        ];
    }
}
