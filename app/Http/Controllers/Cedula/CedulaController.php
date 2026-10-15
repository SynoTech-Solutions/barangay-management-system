<?php

namespace App\Http\Controllers\Cedula;

use App\Enums\FeatureFlag;
use App\Enums\IssuedDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cedula\CedulaBatchRequest;
use App\Http\Requests\Cedula\CedulaCancelRequest;
use App\Http\Requests\Cedula\CedulaQuoteRequest;
use App\Http\Requests\Cedula\CedulaReferenceRequest;
use App\Http\Requests\Cedula\CedulaRequest;
use App\Http\Requests\Documents\DocumentListRequest;
use App\Http\Resources\Cedula\CedulaReferenceResource;
use App\Http\Resources\Cedula\CedulaResource;
use App\Http\Resources\Cedula\CedulaTemplateResource;
use App\Http\Support\EnumOptions;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\Cedula;
use App\Models\IssuedDocument;
use App\Queries\CedulaListQuery;
use App\Queries\CedulaPrintQuery;
use App\Queries\CedulaReferenceQuery;
use App\Queries\CedulaTemplateQuery;
use App\Queries\ResidentPickerQuery;
use App\Services\Cedula\CedulaService;
use App\Services\Features\FeatureFlags;
use App\Support\RouteNames;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CedulaController extends Controller
{
    public function __construct(private readonly CedulaService $cedulas) {}

    public function index(DocumentListRequest $request, CedulaListQuery $query): Response
    {
        $filters = $request->filters();

        return Inertia::render('cedulas/Index', [
            'cedulas' => Paginated::from($query->paginate($filters, $request->perPage()), CedulaResource::class),
            'total' => $query->total($filters),
            'filters' => $filters->toArray(),
            'statuses' => EnumOptions::of(IssuedDocumentStatus::cases()),
        ]);
    }

    public function create(Request $request, ResidentPickerQuery $residents, CedulaTemplateQuery $templates, FeatureFlags $features): Response
    {
        return Inertia::render('cedulas/Form', [
            'resident' => $residents->option($request->filled('resident_id') ? $request->integer('resident_id') : null),
            'templates' => $features->enabled(FeatureFlag::CedulaTemplates) ? array_values(CedulaTemplateResource::collection($templates->active())->resolve()) : [],
        ]);
    }

    public function quote(CedulaQuoteRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->cedulas->quote($request->inputs(), CarbonImmutable::now())->toArray()]);
    }

    public function references(CedulaReferenceRequest $request, CedulaReferenceQuery $query): JsonResponse
    {
        return response()->json(['data' => array_values(CedulaReferenceResource::collection($query->forResident($request->residentId()))->resolve())]);
    }

    public function store(CedulaRequest $request): RedirectResponse
    {
        $cedula = $this->cedulas->issue($request->residentId(), $request->inputs(), $request->formNumber(), $this->actor($request));
        Toast::success('messages.cedula_issued', ['number' => $cedula->cedula_number]);

        return to_route(RouteNames::CEDULAS_SHOW, $cedula);
    }

    public function show(Cedula $cedula): Response
    {
        $cedula->load(['resident:id,full_name,resident_number,deleted_at', ...IssuedDocument::LIFECYCLE_RELATIONS]);

        return Inertia::render('cedulas/Show', ['cedula' => (new CedulaResource($cedula))->resolve()]);
    }

    public function print(Request $request, Cedula $cedula, CedulaPrintQuery $print): Response
    {
        $cedula = $this->cedulas->recordPrint($cedula, $this->actor($request));

        return Inertia::render('cedulas/Print', $print->data($cedula));
    }

    public function release(CedulaBatchRequest $request): RedirectResponse
    {
        $count = $this->cedulas->release($request->ids(), $this->actor($request));
        Toast::success('messages.cedulas_released', ['count' => $count]);

        return back();
    }

    public function cancel(CedulaCancelRequest $request): RedirectResponse
    {
        $count = $this->cedulas->cancel($request->ids(), $request->reason(), $this->actor($request));
        Toast::success('messages.cedulas_cancelled', ['count' => $count]);

        return back();
    }
}
