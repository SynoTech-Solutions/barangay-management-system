<?php

namespace App\Queries;

use App\Enums\CivilStatus;
use App\Enums\Gender;
use App\Enums\LookupType;
use App\Models\Resident;

final readonly class ResidentFormOptionsQuery
{
    private const array DETAIL_TYPES = [
        LookupType::Sector,
        LookupType::Relationship,
        LookupType::School,
        LookupType::Course,
        LookupType::Company,
        LookupType::Position,
        LookupType::EmploymentStatus,
    ];

    public function __construct(private FormOptionsQuery $options) {}

    /**
     * @return array{lookups: array<string, list<array{value: int, label: string}>>, puroks: list<array{value: int, label: string}>, genders: list<array<string, mixed>>, civil_statuses: list<array<string, mixed>>}
     */
    public function for(?Resident $resident): array
    {
        $keep = $resident === null ? [] : array_values(array_filter(
            array_map(fn (string $attribute): mixed => $resident->getAttribute($attribute), array_keys(Resident::LOOKUP_ATTRIBUTES)),
            fn (mixed $id): bool => is_int($id),
        ));

        return [
            'lookups' => $this->options->lookups([...array_values(Resident::LOOKUP_ATTRIBUTES), ...self::DETAIL_TYPES], $keep),
            'puroks' => $this->options->puroks($resident?->purok_id),
            'genders' => array_map(fn (Gender $gender): array => ['value' => $gender->value, 'label' => __($gender->labelKey())], Gender::cases()),
            'civil_statuses' => array_map(fn (CivilStatus $status): array => ['value' => $status->value, 'label' => __($status->labelKey())], CivilStatus::cases()),
        ];
    }
}
