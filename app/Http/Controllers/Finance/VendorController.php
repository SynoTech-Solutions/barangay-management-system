<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SearchRequest;
use App\Http\Requests\Finance\VendorRequest;
use App\Http\Resources\Finance\VendorResource;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\Vendor;
use App\Queries\DocumentPrintContext;
use App\Queries\VendorListQuery;
use App\Services\Finance\VendorService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function __construct(private readonly VendorService $vendors) {}

    public function index(SearchRequest $request, VendorListQuery $query): Response
    {
        return Inertia::render('finance/vendors/Index', [
            'vendors' => Paginated::from($query->paginate($request->search(), $request->perPage()), VendorResource::class),
            'filters' => ['search' => $request->search()],
        ]);
    }

    public function print(SearchRequest $request, VendorListQuery $query, DocumentPrintContext $context): Response
    {
        return Inertia::render('finance/vendors/ListPrint', [
            'header' => $context->header(),
            'vendors' => array_values(VendorResource::collection($query->forPrint($request->search()))->resolve()),
            'limit' => VendorListQuery::PRINT_LIMIT,
            'printedAt' => CarbonImmutable::now()->toIso8601String(),
        ]);
    }

    public function store(VendorRequest $request): RedirectResponse
    {
        $this->vendors->save(null, $request->details(), $this->actor($request));
        Toast::success('messages.vendor_saved');

        return back();
    }

    public function update(VendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $this->vendors->save($vendor, $request->details(), $this->actor($request));
        Toast::success('messages.vendor_saved');

        return back();
    }

    public function deactivate(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->vendors->setActive($vendor, false, $this->actor($request));
        Toast::success('messages.record_deactivated');

        return back();
    }

    public function reactivate(Request $request, Vendor $vendor): RedirectResponse
    {
        $this->vendors->setActive($vendor, true, $this->actor($request));
        Toast::success('messages.record_reactivated');

        return back();
    }
}
