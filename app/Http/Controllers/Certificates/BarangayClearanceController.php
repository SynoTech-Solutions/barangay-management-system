<?php

namespace App\Http\Controllers\Certificates;

use App\Enums\IssuedDocumentStatus;
use App\Enums\LookupType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Certificates\BarangayClearanceRequest;
use App\Http\Requests\Certificates\ClearanceBatchRequest;
use App\Http\Requests\Certificates\ClearanceCancelRequest;
use App\Http\Requests\Documents\DocumentListRequest;
use App\Http\Resources\Certificates\BarangayClearanceResource;
use App\Http\Support\EnumOptions;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\BarangayClearance;
use App\Queries\BarangayClearanceListQuery;
use App\Queries\BarangayClearancePrintQuery;
use App\Queries\FormOptionsQuery;
use App\Queries\ResidentPickerQuery;
use App\Services\Certificates\BarangayClearanceService;
use App\Support\RouteNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BarangayClearanceController extends Controller
{
    private const array LOOKUP_TYPES = [LookupType::ClearanceType, LookupType::ClearancePurpose];

    public function __construct(private readonly BarangayClearanceService $clearances) {}

    public function index(DocumentListRequest $request, BarangayClearanceListQuery $query, FormOptionsQuery $options): Response
    {
        $filters = $request->filters();

        return Inertia::render('clearances/Index', [
            'clearances' => Paginated::from($query->paginate($filters, $request->perPage()), BarangayClearanceResource::class),
            'totals' => $query->totals($filters),
            'filters' => $filters->toArray(),
            'statuses' => EnumOptions::of(IssuedDocumentStatus::cases()),
            'lookups' => $options->lookups(self::LOOKUP_TYPES),
        ]);
    }

    public function create(Request $request, ResidentPickerQuery $residents, FormOptionsQuery $options): Response
    {
        return Inertia::render('clearances/Form', [
            'clearance' => null,
            'resident' => $residents->option($request->filled('resident_id') ? $request->integer('resident_id') : null),
            'lookups' => $options->lookups(self::LOOKUP_TYPES),
        ]);
    }

    public function store(BarangayClearanceRequest $request): RedirectResponse
    {
        $clearance = $this->clearances->issue($request->residentId(), $request->details(), $this->actor($request));
        Toast::success('messages.clearance_issued', ['number' => $clearance->clearance_number]);

        return to_route(RouteNames::CLEARANCES_INDEX);
    }

    public function edit(BarangayClearance $clearance, ResidentPickerQuery $residents, FormOptionsQuery $options): Response
    {
        return Inertia::render('clearances/Form', [
            'clearance' => (new BarangayClearanceResource($clearance))->resolve(),
            'resident' => $residents->option($clearance->resident_id),
            'lookups' => $options->lookups(self::LOOKUP_TYPES, [$clearance->clearance_type_id, $clearance->purpose_id]),
        ]);
    }

    public function update(BarangayClearanceRequest $request, BarangayClearance $clearance): RedirectResponse
    {
        $this->clearances->update($clearance, $request->details(), $this->actor($request));
        Toast::success('messages.clearance_updated');

        return to_route(RouteNames::CLEARANCES_INDEX);
    }

    public function print(Request $request, BarangayClearance $clearance, BarangayClearancePrintQuery $print): Response
    {
        $clearance = $this->clearances->recordPrint($clearance, $this->actor($request));

        return Inertia::render('clearances/Print', $print->data($clearance));
    }

    public function release(ClearanceBatchRequest $request): RedirectResponse
    {
        $count = $this->clearances->release($request->ids(), $this->actor($request));
        Toast::success('messages.clearances_released', ['count' => $count]);

        return back();
    }

    public function cancel(ClearanceCancelRequest $request): RedirectResponse
    {
        $count = $this->clearances->cancel($request->ids(), $request->reason(), $this->actor($request));
        Toast::success('messages.clearances_cancelled', ['count' => $count]);

        return back();
    }
}
