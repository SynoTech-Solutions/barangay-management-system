<?php

namespace App\Http\Controllers\Certificates;

use App\Enums\IssuedDocumentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Businesses\BusinessSearchRequest;
use App\Http\Requests\Certificates\BusinessClearanceBatchRequest;
use App\Http\Requests\Certificates\BusinessClearanceCancelRequest;
use App\Http\Requests\Certificates\BusinessClearanceRequest;
use App\Http\Requests\Documents\DocumentListRequest;
use App\Http\Resources\Businesses\BusinessResource;
use App\Http\Resources\Certificates\BusinessClearanceResource;
use App\Http\Support\EnumOptions;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\BusinessClearance;
use App\Queries\BusinessClearanceListQuery;
use App\Queries\BusinessClearancePrintQuery;
use App\Queries\BusinessPickerQuery;
use App\Queries\ExpiredBusinessQuery;
use App\Services\Certificates\BusinessClearanceService;
use App\Support\RouteNames;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessClearanceController extends Controller
{
    public function __construct(private readonly BusinessClearanceService $clearances) {}

    public function index(DocumentListRequest $request, BusinessClearanceListQuery $query): Response
    {
        $filters = $request->filters();

        return Inertia::render('business-clearances/Index', [
            'clearances' => Paginated::from($query->paginate($filters, $request->perPage()), BusinessClearanceResource::class),
            'totals' => $query->totals($filters),
            'filters' => $filters->toArray(),
            'statuses' => EnumOptions::of(IssuedDocumentStatus::cases()),
        ]);
    }

    public function expired(BusinessSearchRequest $request, ExpiredBusinessQuery $query): Response
    {
        return Inertia::render('business-clearances/Expired', [
            'businesses' => Paginated::from($query->paginate(CarbonImmutable::today(), $request->perPage()), BusinessResource::class),
        ]);
    }

    public function create(Request $request, BusinessPickerQuery $businesses): Response
    {
        return Inertia::render('business-clearances/Form', [
            'clearance' => null,
            'business' => $businesses->option($request->filled('business_id') ? $request->integer('business_id') : null),
        ]);
    }

    public function store(BusinessClearanceRequest $request): RedirectResponse
    {
        $clearance = $this->clearances->issue($request->businessId(), $request->details(), $this->actor($request));
        Toast::success('messages.business_clearance_issued', ['number' => $clearance->clearance_number]);

        return to_route(RouteNames::BUSINESS_CLEARANCES_INDEX);
    }

    public function edit(BusinessClearance $businessClearance, BusinessPickerQuery $businesses): Response
    {
        return Inertia::render('business-clearances/Form', [
            'clearance' => (new BusinessClearanceResource($businessClearance))->resolve(),
            'business' => $businesses->option($businessClearance->business_id),
        ]);
    }

    public function update(BusinessClearanceRequest $request, BusinessClearance $businessClearance): RedirectResponse
    {
        $this->clearances->update($businessClearance, $request->details(), $this->actor($request));
        Toast::success('messages.business_clearance_updated');

        return to_route(RouteNames::BUSINESS_CLEARANCES_INDEX);
    }

    public function print(Request $request, BusinessClearance $businessClearance, BusinessClearancePrintQuery $print): Response
    {
        $clearance = $this->clearances->recordPrint($businessClearance, $this->actor($request));

        return Inertia::render('business-clearances/Print', $print->data($clearance));
    }

    public function release(BusinessClearanceBatchRequest $request): RedirectResponse
    {
        $count = $this->clearances->release($request->ids(), $this->actor($request));
        Toast::success('messages.business_clearances_released', ['count' => $count]);

        return back();
    }

    public function cancel(BusinessClearanceCancelRequest $request): RedirectResponse
    {
        $count = $this->clearances->cancel($request->ids(), $request->reason(), $this->actor($request));
        Toast::success('messages.business_clearances_cancelled', ['count' => $count]);

        return back();
    }
}
