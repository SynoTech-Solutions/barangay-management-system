<?php

namespace App\Http\Controllers\Reports;

use App\Enums\FeatureFlag;
use App\Enums\LookupType;
use App\Enums\ResidentFlag;
use App\Enums\ResidentSort;
use App\Http\Controllers\Controller;
use App\Http\Requests\Residents\ResidentListRequest;
use App\Http\Resources\Residents\ResidentListItemResource;
use App\Http\Support\Paginated;
use App\Models\Resident;
use App\Queries\ResidentFormOptionsQuery;
use App\Queries\ResidentListQuery;
use App\Services\Features\FeatureFlags;
use App\Services\Reports\ResidenceStatistics;
use App\Tenancy\CurrentBarangay;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public const int PRINT_LIMIT = 1000;

    private const string ATTRIBUTE_SUFFIX = '_id';

    public function __construct(private readonly FeatureFlags $features) {}

    public function statistics(ResidenceStatistics $statistics): Response
    {
        return Inertia::render('reports/Statistics', [
            'statistics' => $statistics->compute(CarbonImmutable::today(), $this->features->enabled(FeatureFlag::SectorTagging)),
        ]);
    }

    public function residents(ResidentListRequest $request, ResidentListQuery $query, ResidentFormOptionsQuery $options): Response
    {
        $filters = $request->filters(CarbonImmutable::today(), $this->features->enabled(FeatureFlag::SectorTagging));

        return Inertia::render('reports/Residents', [
            'residents' => Paginated::from($query->paginate($filters, $request->perPage()), ResidentListItemResource::class),
            'filters' => $filters->toArray(),
            'options' => $this->options($options),
        ]);
    }

    public function printResidents(ResidentListRequest $request, ResidentListQuery $query, ResidentFormOptionsQuery $options, CurrentBarangay $currentBarangay): Response
    {
        $filters = $request->filters(CarbonImmutable::today(), $this->features->enabled(FeatureFlag::SectorTagging));
        $builder = $query->builder($filters);

        return Inertia::render('reports/ResidentsPrint', [
            'residents' => array_values(ResidentListItemResource::collection((clone $builder)->with('purok:id,name')->limit(self::PRINT_LIMIT)->get())->resolve()),
            'total' => $builder->count(),
            'limit' => self::PRINT_LIMIT,
            'filters' => $filters->toArray(),
            'options' => $this->options($options),
            'barangay' => $currentBarangay->require()->name,
            'printedAt' => CarbonImmutable::now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(ResidentFormOptionsQuery $options): array
    {
        $formOptions = $options->for(null);
        $attributes = [];
        foreach (Resident::LOOKUP_ATTRIBUTES as $attribute => $type) {
            $attributes[] = [
                'value' => $attribute,
                'label' => (string) __("residents.report_attributes.{$this->attributeKey($attribute)}"),
                'options' => $formOptions['lookups'][$type->value] ?? [],
            ];
        }

        return [
            'puroks' => $formOptions['puroks'],
            'sectors' => $formOptions['lookups'][LookupType::Sector->value] ?? [],
            'genders' => $formOptions['genders'],
            'civil_statuses' => $formOptions['civil_statuses'],
            'flags' => array_map(fn (ResidentFlag $flag): array => ['value' => $flag->value, 'label' => __($flag->labelKey())], ResidentFlag::cases()),
            'sorts' => array_map(fn (ResidentSort $sort): array => ['value' => $sort->value, 'label' => __($sort->labelKey())], ResidentSort::cases()),
            'attributes' => $attributes,
        ];
    }

    private function attributeKey(string $attribute): string
    {
        return str_ends_with($attribute, self::ATTRIBUTE_SUFFIX) ? substr($attribute, 0, -strlen(self::ATTRIBUTE_SUFFIX)) : $attribute;
    }
}
