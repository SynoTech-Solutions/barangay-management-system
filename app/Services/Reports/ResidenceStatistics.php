<?php

namespace App\Services\Reports;

use App\Domain\Residents\AgeBands;
use App\Domain\Residents\ResidentAge;
use App\Enums\CivilStatus;
use App\Enums\Gender;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class ResidenceStatistics
{
    private const array FLAG_COLUMNS = [
        'senior_member' => 'is_senior_citizen_member',
        'pwd' => 'is_pwd',
        'single_parent' => 'is_single_parent',
        'indigent' => 'is_indigent',
        'voter' => 'is_registered_voter',
        'permanent_resident' => 'is_permanent_resident',
        'living_with_parents' => 'is_living_with_parents',
    ];

    private const string SENIOR = 'senior';

    private const string UNKNOWN = 'unknown';

    /**
     * @return array{population: int, deceased: int, flags: array<string, int>, genders: list<array{key: string, label: string, total: int}>, civil_statuses: list<array{key: string, label: string, total: int}>, age_bands: list<array{key: string, label: string, total: int}>, puroks: list<array{key: string, label: string, total: int}>, sectors: list<array{key: string, label: string, total: int}>|null}
     */
    public function compute(CarbonImmutable $today, bool $withSectors): array
    {
        $seniorCutoff = $today->subYears(ResidentAge::SENIOR_CITIZEN_AGE)->toDateString();

        $selects = ['count(*) filter (where not is_deceased) as population', 'count(*) filter (where is_deceased) as deceased'];
        foreach (self::FLAG_COLUMNS as $key => $column) {
            $selects[] = "count(*) filter (where not is_deceased and {$column}) as {$key}";
        }
        $selects[] = 'count(*) filter (where not is_deceased and birthdate <= ?) as '.self::SENIOR;

        $totals = (array) Resident::query()->toBase()->selectRaw(implode(', ', $selects), [$seniorCutoff])->first();

        $flags = [self::SENIOR => (int) ($totals[self::SENIOR] ?? 0)];
        foreach (array_keys(self::FLAG_COLUMNS) as $key) {
            $flags[$key] = (int) ($totals[$key] ?? 0);
        }

        return [
            'population' => (int) ($totals['population'] ?? 0),
            'deceased' => (int) ($totals['deceased'] ?? 0),
            'flags' => $flags,
            'genders' => $this->enumCounts('gender', Gender::cases()),
            'civil_statuses' => $this->enumCounts('civil_status', CivilStatus::cases()),
            'age_bands' => $this->ageBands($today),
            'puroks' => $this->purokCounts(),
            'sectors' => $withSectors ? $this->sectorCounts() : null,
        ];
    }

    /**
     * @param  list<Gender>|list<CivilStatus>  $cases
     * @return list<array{key: string, label: string, total: int}>
     */
    private function enumCounts(string $column, array $cases): array
    {
        $counts = $this->living()
            ->select([$column.' as bucket'])
            ->selectRaw('count(*) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $rows = array_map(fn (Gender|CivilStatus $case): array => [
            'key' => $case->value,
            'label' => (string) __($case->labelKey()),
            'total' => (int) ($counts[$case->value] ?? 0),
        ], $cases);
        $rows[] = ['key' => self::UNKNOWN, 'label' => (string) __('residents.statistics.unknown'), 'total' => (int) ($counts[''] ?? 0)];

        return $rows;
    }

    /**
     * @return list<array{key: string, label: string, total: int}>
     */
    private function ageBands(CarbonImmutable $today): array
    {
        $byAge = [];
        $rows = $this->living()
            ->selectRaw("cast(date_part('year', age(cast(? as date), birthdate)) as integer) as years, count(*) as total", [$today->toDateString()])
            ->groupBy('years')
            ->get();

        foreach ($rows as $row) {
            $key = is_numeric($row->years) ? (int) $row->years : self::UNKNOWN;
            $byAge[$key] = ($byAge[$key] ?? 0) + (int) $row->total;
        }

        $bands = [];
        foreach (AgeBands::fold($byAge) as $label => $total) {
            $bands[] = [
                'key' => $label,
                'label' => $label === AgeBands::UNKNOWN ? (string) __('residents.statistics.unknown') : $label,
                'total' => $total,
            ];
        }

        return $bands;
    }

    /**
     * @return list<array{key: string, label: string, total: int}>
     */
    private function purokCounts(): array
    {
        return $this->labelled($this->living()
            ->leftJoin('puroks', 'puroks.id', '=', 'residents.purok_id')
            ->selectRaw('residents.purok_id as bucket, puroks.name as label, count(*) as total')
            ->groupBy('residents.purok_id', 'puroks.name')
            ->orderBy('puroks.name'));
    }

    /**
     * @return list<array{key: string, label: string, total: int}>
     */
    private function sectorCounts(): array
    {
        return $this->labelled($this->living()
            ->join('resident_sector', 'resident_sector.resident_id', '=', 'residents.id')
            ->join('lookup_values', 'lookup_values.id', '=', 'resident_sector.sector_id')
            ->selectRaw('lookup_values.id as bucket, lookup_values.label as label, count(distinct residents.id) as total')
            ->groupBy('lookup_values.id', 'lookup_values.label')
            ->orderBy('lookup_values.label'));
    }

    /**
     * @return list<array{key: string, label: string, total: int}>
     */
    private function labelled(QueryBuilder $query): array
    {
        return array_values(array_map(fn (object $row): array => [
            'key' => $row->bucket === null ? self::UNKNOWN : (string) $row->bucket,
            'label' => $row->label === null ? (string) __('residents.statistics.unknown') : (string) $row->label,
            'total' => (int) $row->total,
        ], $query->get()->all()));
    }

    private function living(): QueryBuilder
    {
        return Resident::query()->living()->toBase();
    }
}
