<?php

namespace App\Services\Residents;

use App\Domain\Residents\ResidentAge;
use App\Enums\AuditAction;
use App\Models\Resident;
use App\Models\User;
use App\Queries\Filters\ResidentListFilters;
use App\Queries\ResidentListQuery;
use App\Services\Audit\AuditTrail;
use Generator;

final readonly class ResidentCsvExporter
{
    public const array COLUMNS = [
        'resident_number', 'last_name', 'first_name', 'middle_name', 'name_extension', 'birthdate', 'age', 'gender',
        'civil_status', 'purok', 'street_address', 'mobile_number', 'email', 'registered_voter', 'precinct_number',
        'senior', 'senior_member', 'pwd', 'indigent', 'single_parent', 'deceased', 'sectors',
    ];

    private const int CHUNK_SIZE = 500;

    private const string FORMULA_PREFIXES = '=+-@';

    private const string FORMULA_GUARD = "'";

    private const string LIST_SEPARATOR = '; ';

    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private ResidentListQuery $query,
        private AuditTrail $audit,
    ) {}

    public function recordExport(ResidentListFilters $filters, int $barangayId, User $actor): void
    {
        $this->audit->record(AuditAction::Exported, after: ['subject' => 'residents', 'filters' => $filters->toArray()], barangayId: $barangayId, actorId: $actor->id);
    }

    /**
     * @return list<string>
     */
    public function header(): array
    {
        return array_map(fn (string $column): string => (string) __("residents.export.{$column}"), self::COLUMNS);
    }

    /**
     * @return Generator<int, list<string>>
     */
    public function rows(ResidentListFilters $filters): Generator
    {
        $residents = $this->query->builder($filters)
            ->with(['purok:id,name', 'sectors:id,label'])
            ->lazy(self::CHUNK_SIZE);

        foreach ($residents as $resident) {
            yield array_map(fn (mixed $value): string => $this->cell($value), $this->row($resident, $filters));
        }
    }

    /**
     * @return list<mixed>
     */
    private function row(Resident $resident, ResidentListFilters $filters): array
    {
        return [
            $resident->resident_number,
            $resident->last_name,
            $resident->first_name,
            $resident->middle_name,
            $resident->name_extension,
            $resident->birthdate?->format(self::DATE_FORMAT),
            ResidentAge::years($resident->birthdate, $filters->today),
            $resident->gender === null ? null : __($resident->gender->labelKey()),
            $resident->civil_status === null ? null : __($resident->civil_status->labelKey()),
            $resident->purok?->name,
            $resident->street_address,
            $resident->mobile_number,
            $resident->email,
            $resident->is_registered_voter,
            $resident->precinct_number,
            ResidentAge::isSenior($resident->birthdate, $filters->today),
            $resident->is_senior_citizen_member,
            $resident->is_pwd,
            $resident->is_indigent,
            $resident->is_single_parent,
            $resident->is_deceased,
            $resident->sectors->pluck('label')->implode(self::LIST_SEPARATOR),
        ];
    }

    private function cell(mixed $value): string
    {
        $text = match (true) {
            $value === null => '',
            is_bool($value) => (string) __($value ? 'residents.export.yes' : 'residents.export.no'),
            is_scalar($value) => (string) $value,
            default => '',
        };

        return $text !== '' && str_contains(self::FORMULA_PREFIXES, $text[0]) ? self::FORMULA_GUARD.$text : $text;
    }
}
