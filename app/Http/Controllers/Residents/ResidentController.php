<?php

namespace App\Http\Controllers\Residents;

use App\Enums\FeatureFlag;
use App\Enums\LookupType;
use App\Enums\Permission;
use App\Enums\ResidentFlag;
use App\Enums\ResidentSort;
use App\Http\Controllers\Controller;
use App\Http\Requests\Residents\ResidentListRequest;
use App\Http\Requests\Residents\ResidentPhotoRequest;
use App\Http\Requests\Residents\ResidentRequest;
use App\Http\Resources\Certificates\BarangayClearanceResource;
use App\Http\Resources\Forms\FormTransactionResource;
use App\Http\Resources\Residents\ResidentListItemResource;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\Resident;
use App\Models\User;
use App\Queries\BarangayClearanceListQuery;
use App\Queries\BlotterListQuery;
use App\Queries\FormTransactionListQuery;
use App\Queries\PoliticalTagQuery;
use App\Queries\ResidentFormOptionsQuery;
use App\Queries\ResidentListQuery;
use App\Queries\ResidentProfileQuery;
use App\Services\Features\FeatureFlags;
use App\Services\Residents\ResidentRegistry;
use App\Support\RouteNames;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResidentController extends Controller
{
    public function __construct(
        private readonly ResidentRegistry $registry,
        private readonly FeatureFlags $features,
    ) {}

    public function index(ResidentListRequest $request, ResidentListQuery $query, ResidentFormOptionsQuery $options, PoliticalTagQuery $tags): Response
    {
        $filters = $request->filters(CarbonImmutable::today(), $this->features->enabled(FeatureFlag::SectorTagging));
        $formOptions = $options->for(null);
        $page = $query->paginate($filters, $request->perPage());
        $actor = $this->actor($request);

        return Inertia::render('residents/Index', [
            'residents' => Paginated::from($page, ResidentListItemResource::class),
            'politicalTags' => $this->canSeeTags($actor)
                ? (object) $tags->forResidents(array_values(array_map(fn (Resident $resident): int => $resident->id, $page->items())))
                : null,
            'colorCategories' => $this->canManageTags($actor) ? $tags->activeCategories() : [],
            'filters' => $filters->toArray(),
            'options' => [
                'puroks' => $formOptions['puroks'],
                'sectors' => $formOptions['lookups'][LookupType::Sector->value] ?? [],
                'genders' => $formOptions['genders'],
                'civil_statuses' => $formOptions['civil_statuses'],
                'flags' => array_map(fn (ResidentFlag $flag): array => ['value' => $flag->value, 'label' => __($flag->labelKey())], ResidentFlag::cases()),
                'sorts' => array_map(fn (ResidentSort $sort): array => ['value' => $sort->value, 'label' => __($sort->labelKey())], ResidentSort::cases()),
            ],
        ]);
    }

    public function create(ResidentFormOptionsQuery $options): Response
    {
        return Inertia::render('residents/Form', [
            'resident' => null,
            'familyMembers' => [],
            'educations' => [],
            'workHistories' => [],
            'options' => $options->for(null),
            'limits' => $this->limits(),
        ]);
    }

    public function store(ResidentRequest $request): RedirectResponse
    {
        $resident = $this->registry->register($request->profile(), $request->spouseId(), $this->sectorIds($request), $this->actor($request));

        Toast::success('messages.resident_created', ['number' => $resident->resident_number]);

        return to_route(RouteNames::RESIDENTS_EDIT, $resident);
    }

    public function show(Request $request, Resident $resident, ResidentProfileQuery $profile, PoliticalTagQuery $tags, BarangayClearanceListQuery $clearances, BlotterListQuery $blotters, FormTransactionListQuery $forms): Response
    {
        $showForms = $this->features->enabled(FeatureFlag::FormsEngine) && $this->actor($request)->can(Permission::FormTransactionsView->value);
        $details = $profile->details($resident, $this->features->enabled(FeatureFlag::ResidentWorkEducation));
        $showClearances = $this->features->enabled(FeatureFlag::BarangayClearance) && $this->actor($request)->can(Permission::ClearancesView->value);
        $showBlotters = $this->features->enabled(FeatureFlag::Blotter) && $this->actor($request)->can(Permission::BlotterView->value);

        return Inertia::render('residents/Show', [
            'blotters' => $showBlotters ? $blotters->forResident($resident) : null,
            'clearances' => $showClearances ? array_values(BarangayClearanceResource::collection($clearances->forResident($resident))->resolve()) : null,
            'forms' => $showForms ? array_values(FormTransactionResource::collection($forms->forResident($resident))->resolve()) : null,
            'politicalTag' => $this->canSeeTags($this->actor($request)) ? ($tags->forResidents([$resident->id])[$resident->id] ?? null) : null,
            'resident' => $details['resident'],
            'familyMembers' => $details['family_members'],
            'educations' => $details['educations'] ?? [],
            'workHistories' => $details['work_histories'] ?? [],
        ]);
    }

    public function edit(Resident $resident, ResidentProfileQuery $profile, ResidentFormOptionsQuery $options): Response
    {
        $details = $profile->details($resident, $this->features->enabled(FeatureFlag::ResidentWorkEducation));

        return Inertia::render('residents/Form', [
            'resident' => $details['resident'],
            'familyMembers' => $details['family_members'],
            'educations' => $details['educations'] ?? [],
            'workHistories' => $details['work_histories'] ?? [],
            'options' => $options->for($resident),
            'limits' => $this->limits(),
        ]);
    }

    public function update(ResidentRequest $request, Resident $resident): RedirectResponse
    {
        $this->registry->update($resident, $request->profile(), $request->spouseId(), $this->sectorIds($request), $this->actor($request));

        Toast::success('messages.resident_updated');

        return to_route(RouteNames::RESIDENTS_EDIT, $resident);
    }

    public function destroy(Request $request, Resident $resident): RedirectResponse
    {
        $this->registry->delete($resident, $this->actor($request));

        Toast::success('messages.resident_deleted');

        return to_route(RouteNames::RESIDENTS_INDEX);
    }

    public function restore(Request $request, Resident $resident): RedirectResponse
    {
        $this->registry->restore($resident, $this->actor($request));

        Toast::success('messages.resident_restored');

        return to_route(RouteNames::RESIDENTS_SHOW, $resident);
    }

    private function canSeeTags(User $actor): bool
    {
        return $this->features->enabled(FeatureFlag::PoliticalColorTagging) && $actor->can(Permission::PoliticalTagsView->value);
    }

    private function canManageTags(User $actor): bool
    {
        return $this->canSeeTags($actor) && $actor->can(Permission::PoliticalTagsManage->value);
    }

    /**
     * @return array{photo_max_kilobytes: int}
     */
    private function limits(): array
    {
        return ['photo_max_kilobytes' => ResidentPhotoRequest::PHOTO_MAX_KILOBYTES];
    }

    /**
     * @return list<int>|null
     */
    private function sectorIds(ResidentRequest $request): ?array
    {
        return $this->features->enabled(FeatureFlag::SectorTagging) ? $request->sectorIds() : null;
    }
}
