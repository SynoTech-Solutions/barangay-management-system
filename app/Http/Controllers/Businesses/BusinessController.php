<?php

namespace App\Http\Controllers\Businesses;

use App\Enums\BusinessStatus;
use App\Enums\LookupType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Businesses\BusinessListRequest;
use App\Http\Requests\Businesses\BusinessRequest;
use App\Http\Requests\Businesses\BusinessStatusRequest;
use App\Http\Resources\Businesses\BusinessResource;
use App\Http\Resources\Certificates\BusinessClearanceResource;
use App\Http\Support\EnumOptions;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\Business;
use App\Queries\BusinessClearanceListQuery;
use App\Queries\BusinessListQuery;
use App\Queries\FormOptionsQuery;
use App\Queries\ResidentPickerQuery;
use App\Services\Businesses\BusinessRegistry;
use App\Support\RouteNames;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    private const array LOOKUP_TYPES = [LookupType::BusinessCategory, LookupType::BusinessNature, LookupType::BusinessOwnershipType];

    public function __construct(private readonly BusinessRegistry $registry) {}

    public function index(BusinessListRequest $request, BusinessListQuery $query, FormOptionsQuery $options): Response
    {
        $filters = $request->filters();

        return Inertia::render('businesses/Index', [
            'businesses' => Paginated::from($query->paginate($filters, $request->perPage()), BusinessResource::class),
            'filters' => $filters->toArray(),
            'options' => $this->options($options, null),
        ]);
    }

    public function show(Business $business, BusinessClearanceListQuery $clearances): Response
    {
        $business->load(Business::RELATIONS);

        return Inertia::render('businesses/Show', [
            'business' => (new BusinessResource($business))->resolve(),
            'clearances' => array_values(BusinessClearanceResource::collection($clearances->forBusiness($business))->resolve()),
        ]);
    }

    public function create(FormOptionsQuery $options): Response
    {
        return Inertia::render('businesses/Form', [
            'business' => null,
            'owner' => null,
            'options' => $this->options($options, null),
        ]);
    }

    public function store(BusinessRequest $request): RedirectResponse
    {
        $business = $this->registry->register($request->details(), $this->actor($request));
        Toast::success('messages.business_registered', ['number' => $business->business_number]);

        return to_route(RouteNames::BUSINESSES_SHOW, $business);
    }

    public function edit(Business $business, FormOptionsQuery $options, ResidentPickerQuery $residents): Response
    {
        return Inertia::render('businesses/Form', [
            'business' => (new BusinessResource($business))->resolve(),
            'owner' => $residents->option($business->owner_resident_id),
            'options' => $this->options($options, $business),
        ]);
    }

    public function update(BusinessRequest $request, Business $business): RedirectResponse
    {
        $this->registry->update($business, $request->details(), $this->actor($request));
        Toast::success('messages.business_updated');

        return to_route(RouteNames::BUSINESSES_SHOW, $business);
    }

    public function status(BusinessStatusRequest $request, Business $business): RedirectResponse
    {
        $this->registry->changeStatus($business, $request->status(), $this->actor($request));
        Toast::success('messages.business_status_changed');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function options(FormOptionsQuery $options, ?Business $business): array
    {
        return [
            'lookups' => $options->lookups(self::LOOKUP_TYPES, $business === null ? [] : [$business->category_id, $business->nature_id, $business->ownership_type_id]),
            'puroks' => $options->puroks($business?->purok_id),
            'statuses' => EnumOptions::of(BusinessStatus::cases()),
        ];
    }
}
