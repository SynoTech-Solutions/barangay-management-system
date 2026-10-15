<?php

namespace App\Http\Controllers\Finance;

use App\Enums\CheckStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CheckListRequest;
use App\Http\Requests\Finance\CheckReleaseRequest;
use App\Http\Requests\Finance\CheckRequest;
use App\Http\Requests\Finance\ReasonRequest;
use App\Http\Resources\Finance\CheckResource;
use App\Http\Resources\Finance\VoucherResource;
use App\Http\Support\EnumOptions;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\Check;
use App\Models\Voucher;
use App\Queries\CheckListQuery;
use App\Queries\DisbursementFormQuery;
use App\Queries\DisbursementPrintQuery;
use App\Queries\VoucherListQuery;
use App\Services\Finance\CheckService;
use App\Support\RouteNames;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckController extends Controller
{
    public function __construct(private readonly CheckService $checks) {}

    public function index(CheckListRequest $request, CheckListQuery $query): Response
    {
        $filters = $request->filters();

        return Inertia::render('finance/checks/Index', [
            'checks' => Paginated::from($query->paginate($filters, $request->perPage()), CheckResource::class),
            'total' => $query->total($filters),
            'filters' => $filters->toArray(),
            'statuses' => EnumOptions::of(CheckStatus::cases()),
        ]);
    }

    public function create(Voucher $voucher, VoucherListQuery $vouchers, DisbursementFormQuery $form): Response
    {
        return Inertia::render('finance/checks/Form', [
            'check' => null,
            'voucher' => (new VoucherResource($vouchers->find($voucher->id)))->resolve(),
            'bankAccounts' => $form->bankAccounts(),
            'vendors' => [],
            'minimum' => CheckRequest::AMOUNT_MIN,
            'today' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    public function store(CheckRequest $request, Voucher $voucher): RedirectResponse
    {
        $check = $this->checks->issueForVoucher($voucher, $request->details(), $this->actor($request));
        Toast::success('messages.check_issued', ['number' => $check->check_number, 'amount' => $check->amount]);

        return to_route(RouteNames::VOUCHERS_SHOW, $voucher);
    }

    public function createDirect(DisbursementFormQuery $form): Response
    {
        return Inertia::render('finance/checks/Form', [
            'check' => null,
            'voucher' => null,
            'bankAccounts' => $form->bankAccounts(),
            'vendors' => $form->vendors(),
            'minimum' => CheckRequest::AMOUNT_MIN,
            'today' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    public function storeDirect(CheckRequest $request): RedirectResponse
    {
        $check = $this->checks->issueDirect($request->directDetails(), $this->actor($request));
        Toast::success('messages.check_issued', ['number' => $check->check_number, 'amount' => $check->amount]);

        return to_route(RouteNames::CHECKS_INDEX);
    }

    public function edit(Check $check, VoucherListQuery $vouchers, DisbursementFormQuery $form): Response
    {
        return Inertia::render('finance/checks/Form', [
            'check' => (new CheckResource($check))->resolve(),
            'voucher' => $check->voucher_id === null ? null : (new VoucherResource($vouchers->find($check->voucher_id)))->resolve(),
            'bankAccounts' => $form->bankAccounts($check->bank_account_id),
            'vendors' => $check->isDirect() ? $form->vendors($check->vendor_id) : [],
            'minimum' => CheckRequest::AMOUNT_MIN,
            'today' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    public function update(CheckRequest $request, Check $check): RedirectResponse
    {
        $details = $check->isDirect() ? $request->directDetails() : $request->details();
        $this->checks->update($check, $details, $this->actor($request));
        Toast::success('messages.check_updated', ['number' => $check->check_number]);

        return to_route(RouteNames::CHECKS_INDEX);
    }

    public function cancel(ReasonRequest $request, Check $check): RedirectResponse
    {
        $this->checks->cancel($check, $request->reason(), $this->actor($request));
        Toast::success('messages.check_cancelled', ['number' => $check->check_number]);

        return back();
    }

    public function release(CheckReleaseRequest $request, Check $check): RedirectResponse
    {
        $this->checks->release($check, $request->receivedBy(), $this->actor($request));
        Toast::success('messages.check_released', ['number' => $check->check_number]);

        return back();
    }

    public function print(Request $request, Check $check, DisbursementPrintQuery $print): Response
    {
        $printed = $this->checks->recordPrint($check, $this->actor($request));

        return Inertia::render('finance/checks/Print', $print->check($printed));
    }
}
