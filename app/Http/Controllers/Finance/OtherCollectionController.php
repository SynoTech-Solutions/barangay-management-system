<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CollectionCancelRequest;
use App\Http\Requests\Finance\OtherCollectionListRequest;
use App\Http\Requests\Finance\OtherCollectionRequest;
use App\Http\Resources\Finance\OtherCollectionResource;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\OtherCollection;
use App\Queries\CollectionTypeQuery;
use App\Queries\DocumentPrintContext;
use App\Queries\OtherCollectionListQuery;
use App\Queries\ResidentPickerQuery;
use App\Services\Finance\OtherCollectionService;
use App\Support\RouteNames;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OtherCollectionController extends Controller
{
    public function __construct(private readonly OtherCollectionService $collections) {}

    public function index(OtherCollectionListRequest $request, OtherCollectionListQuery $query, CollectionTypeQuery $types): Response
    {
        $filters = $request->filters();

        return Inertia::render('collections/Index', [
            'collections' => Paginated::from($query->paginate($filters, $request->perPage()), OtherCollectionResource::class),
            'total' => $query->total($filters),
            'filters' => $filters->toArray(),
            'types' => $types->options(),
        ]);
    }

    public function print(OtherCollectionListRequest $request, OtherCollectionListQuery $query, DocumentPrintContext $context): Response
    {
        $filters = $request->filters();

        return Inertia::render('collections/ListPrint', [
            'header' => $context->header(),
            'collections' => array_values(OtherCollectionResource::collection($query->forPrint($filters))->resolve()),
            'count' => $query->count($filters),
            'total' => $query->total($filters),
            'limit' => OtherCollectionListQuery::PRINT_LIMIT,
            'filters' => $filters->toArray(),
            'printedAt' => CarbonImmutable::now()->toIso8601String(),
        ]);
    }

    public function create(CollectionTypeQuery $types): Response
    {
        return Inertia::render('collections/Form', [
            'collection' => null,
            'payer' => null,
            'types' => $types->options(),
            'today' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    public function store(OtherCollectionRequest $request): RedirectResponse
    {
        $collection = $this->collections->record($request->details(), $this->actor($request));
        Toast::success('messages.collection_recorded', ['number' => $collection->collection_number]);

        return to_route(RouteNames::COLLECTIONS_INDEX);
    }

    public function edit(OtherCollection $collection, CollectionTypeQuery $types, ResidentPickerQuery $residents): Response
    {
        return Inertia::render('collections/Form', [
            'collection' => (new OtherCollectionResource($collection))->resolve(),
            'payer' => $residents->option($collection->resident_id),
            'types' => $types->options($collection->collection_type_id),
            'today' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    public function update(OtherCollectionRequest $request, OtherCollection $collection): RedirectResponse
    {
        $this->collections->update($collection, $request->details(), $this->actor($request));
        Toast::success('messages.collection_updated', ['number' => $collection->collection_number]);

        return to_route(RouteNames::COLLECTIONS_INDEX);
    }

    public function cancel(CollectionCancelRequest $request, OtherCollection $collection): RedirectResponse
    {
        $this->collections->cancel($collection, $request->reason(), $this->actor($request));
        Toast::success('messages.collection_cancelled', ['number' => $collection->collection_number]);

        return back();
    }
}
