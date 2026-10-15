<?php

namespace App\Http\Controllers\Certificates;

use App\Enums\IssuedDocumentStatus;
use App\Enums\LookupType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Certificates\IndigencyCertificateBatchRequest;
use App\Http\Requests\Certificates\IndigencyCertificateCancelRequest;
use App\Http\Requests\Certificates\IndigencyCertificateRequest;
use App\Http\Requests\Documents\DocumentListRequest;
use App\Http\Resources\Certificates\IndigencyCertificateResource;
use App\Http\Support\EnumOptions;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\IndigencyCertificate;
use App\Queries\FormOptionsQuery;
use App\Queries\IndigencyCertificateListQuery;
use App\Queries\IndigencyCertificatePrintQuery;
use App\Queries\ResidentPickerQuery;
use App\Services\Certificates\IndigencyCertificateService;
use App\Support\RouteNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndigencyCertificateController extends Controller
{
    private const array LOOKUP_TYPES = [LookupType::CertificatePurpose];

    public function __construct(private readonly IndigencyCertificateService $certificates) {}

    public function index(DocumentListRequest $request, IndigencyCertificateListQuery $query, FormOptionsQuery $options): Response
    {
        $filters = $request->filters();

        return Inertia::render('indigency/Index', [
            'certificates' => Paginated::from($query->paginate($filters, $request->perPage()), IndigencyCertificateResource::class),
            'filters' => $filters->toArray(),
            'statuses' => EnumOptions::of(IssuedDocumentStatus::cases()),
            'lookups' => $options->lookups(self::LOOKUP_TYPES),
        ]);
    }

    public function create(Request $request, ResidentPickerQuery $residents, FormOptionsQuery $options): Response
    {
        return Inertia::render('indigency/Form', [
            'certificate' => null,
            'resident' => $residents->option($request->filled('resident_id') ? $request->integer('resident_id') : null),
            'lookups' => $options->lookups(self::LOOKUP_TYPES),
        ]);
    }

    public function store(IndigencyCertificateRequest $request): RedirectResponse
    {
        $certificate = $this->certificates->issue($request->residentId(), $request->purposeId(), $this->actor($request));
        Toast::success('messages.indigency_issued', ['number' => $certificate->certificate_number]);

        return to_route(RouteNames::INDIGENCY_INDEX);
    }

    public function edit(IndigencyCertificate $certificate, ResidentPickerQuery $residents, FormOptionsQuery $options): Response
    {
        return Inertia::render('indigency/Form', [
            'certificate' => (new IndigencyCertificateResource($certificate))->resolve(),
            'resident' => $residents->option($certificate->resident_id),
            'lookups' => $options->lookups(self::LOOKUP_TYPES, [$certificate->purpose_id]),
        ]);
    }

    public function update(IndigencyCertificateRequest $request, IndigencyCertificate $certificate): RedirectResponse
    {
        $this->certificates->update($certificate, $request->purposeId(), $this->actor($request));
        Toast::success('messages.indigency_updated');

        return to_route(RouteNames::INDIGENCY_INDEX);
    }

    public function print(Request $request, IndigencyCertificate $certificate, IndigencyCertificatePrintQuery $print): Response
    {
        $certificate = $this->certificates->recordPrint($certificate, $this->actor($request));

        return Inertia::render('indigency/Print', $print->data($certificate));
    }

    public function release(IndigencyCertificateBatchRequest $request): RedirectResponse
    {
        $count = $this->certificates->release($request->ids(), $this->actor($request));
        Toast::success('messages.indigency_released', ['count' => $count]);

        return back();
    }

    public function cancel(IndigencyCertificateCancelRequest $request): RedirectResponse
    {
        $count = $this->certificates->cancel($request->ids(), $request->reason(), $this->actor($request));
        Toast::success('messages.indigency_cancelled', ['count' => $count]);

        return back();
    }
}
