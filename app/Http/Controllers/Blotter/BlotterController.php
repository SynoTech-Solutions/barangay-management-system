<?php

namespace App\Http\Controllers\Blotter;

use App\Enums\BlotterStatus;
use App\Enums\FeatureFlag;
use App\Enums\LookupType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Blotter\BlotterCancelRequest;
use App\Http\Requests\Blotter\BlotterListRequest;
use App\Http\Requests\Blotter\BlotterRequest;
use App\Http\Requests\Blotter\BlotterResolveRequest;
use App\Http\Resources\Blotter\BlotterListItemResource;
use App\Http\Resources\Blotter\BlotterResource;
use App\Http\Support\EnumOptions;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\Blotter;
use App\Queries\BlotterListQuery;
use App\Queries\FormOptionsQuery;
use App\Services\Blotter\BlotterService;
use App\Services\Features\FeatureFlags;
use App\Support\RouteNames;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BlotterController extends Controller
{
    private const array LOOKUP_TYPES = [LookupType::BlotterIncidentType];

    public function __construct(private readonly BlotterService $blotters) {}

    public function index(BlotterListRequest $request, BlotterListQuery $query, FormOptionsQuery $options): Response
    {
        $filters = $request->filters();

        return Inertia::render('blotters/Index', [
            'blotters' => Paginated::from($query->paginate($filters, $request->perPage()), BlotterListItemResource::class),
            'filters' => $filters->toArray(),
            'statuses' => EnumOptions::of(BlotterStatus::cases()),
            'lookups' => $options->lookups(self::LOOKUP_TYPES),
        ]);
    }

    public function create(FormOptionsQuery $options): Response
    {
        return Inertia::render('blotters/Form', [
            'blotter' => null,
            'lookups' => $options->lookups(self::LOOKUP_TYPES),
        ]);
    }

    public function store(BlotterRequest $request): RedirectResponse
    {
        $blotter = $this->blotters->record($request->details(), $request->parties(), $this->actor($request));
        Toast::success('messages.blotter_recorded', ['number' => $blotter->blotter_number]);

        return to_route(RouteNames::BLOTTERS_SHOW, $blotter);
    }

    public function show(Blotter $blotter, BlotterListQuery $query, FeatureFlags $features): Response
    {
        return Inertia::render('blotters/Show', [
            'blotter' => (new BlotterResource($query->details($blotter, $features->enabled(FeatureFlag::BlotterPhotoAttachment))))->resolve(),
        ]);
    }

    public function edit(Blotter $blotter, BlotterListQuery $query, FormOptionsQuery $options): Response
    {
        return Inertia::render('blotters/Form', [
            'blotter' => (new BlotterResource($query->details($blotter, false)))->resolve(),
            'lookups' => $options->lookups(self::LOOKUP_TYPES, [$blotter->incident_type_id]),
        ]);
    }

    public function update(BlotterRequest $request, Blotter $blotter): RedirectResponse
    {
        $this->blotters->update($blotter, $request->details(), $request->parties(), $this->actor($request));
        Toast::success('messages.blotter_updated');

        return to_route(RouteNames::BLOTTERS_SHOW, $blotter);
    }

    public function resolve(BlotterResolveRequest $request, Blotter $blotter): RedirectResponse
    {
        $this->blotters->resolve($blotter, $request->notes(), $this->actor($request));
        Toast::success('messages.blotter_resolved');

        return back();
    }

    public function cancel(BlotterCancelRequest $request, Blotter $blotter): RedirectResponse
    {
        $this->blotters->cancel($blotter, $request->reason(), $this->actor($request));
        Toast::success('messages.blotter_cancelled');

        return back();
    }
}
