<?php

namespace App\Http\Controllers\Finance;

use App\Enums\PaymentType;
use App\Enums\VoucherStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ReasonRequest;
use App\Http\Requests\Finance\VoucherListRequest;
use App\Http\Requests\Finance\VoucherRequest;
use App\Http\Resources\Finance\VoucherResource;
use App\Http\Support\EnumOptions;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\Budget;
use App\Models\Voucher;
use App\Queries\DisbursementFormQuery;
use App\Queries\DisbursementPrintQuery;
use App\Queries\ResidentPickerQuery;
use App\Queries\VoucherListQuery;
use App\Services\Finance\VoucherService;
use App\Support\RouteNames;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VoucherController extends Controller
{
    public function __construct(private readonly VoucherService $vouchers) {}

    public function index(VoucherListRequest $request, VoucherListQuery $query): Response
    {
        $filters = $request->filters();

        return Inertia::render('finance/vouchers/Index', [
            'vouchers' => Paginated::from($query->paginate($filters, $request->perPage()), VoucherResource::class),
            'filters' => $filters->toArray(),
            'statuses' => EnumOptions::of(VoucherStatus::cases()),
            'paymentTypes' => EnumOptions::of(PaymentType::cases()),
        ]);
    }

    public function create(Request $request, DisbursementFormQuery $form): Response
    {
        $budgets = $form->budgets();
        $budgetId = $request->filled('budget_id') ? $request->integer('budget_id') : ($budgets[0]['value'] ?? null);

        return Inertia::render('finance/vouchers/Form', [
            'voucher' => null,
            'payee' => null,
            'budgets' => $budgets,
            'budgetId' => $budgetId,
            'fundSources' => $form->fundSources($budgetId === null ? null : Budget::query()->find($budgetId)),
            'vendors' => $form->vendors(),
            'paymentTypes' => EnumOptions::of(PaymentType::cases()),
            'today' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    public function store(VoucherRequest $request): RedirectResponse
    {
        $voucher = $this->vouchers->create($request->details(), $request->lines(), $this->actor($request));
        Toast::success('messages.voucher_prepared', ['number' => $voucher->voucher_number]);

        return to_route(RouteNames::VOUCHERS_SHOW, $voucher);
    }

    public function show(Voucher $voucher, VoucherListQuery $query): Response
    {
        $detail = $query->detail($voucher->id);

        return Inertia::render('finance/vouchers/Show', [
            'voucher' => (new VoucherResource($detail))->resolve(),
            'requiresCheck' => $this->vouchers->requiresCheck($detail),
        ]);
    }

    public function edit(Voucher $voucher, VoucherListQuery $query, DisbursementFormQuery $form, ResidentPickerQuery $residents): Response
    {
        $detail = $query->detail($voucher->id);

        return Inertia::render('finance/vouchers/Form', [
            'voucher' => (new VoucherResource($detail))->resolve(),
            'payee' => $residents->option($detail->resident_id),
            'budgets' => $form->budgets(),
            'budgetId' => $detail->budget_id,
            'fundSources' => $form->fundSources($detail->budget, $detail->items->pluck('account_id')->map(fn (mixed $id): int => (int) $id)->all()),
            'vendors' => $form->vendors($detail->vendor_id),
            'paymentTypes' => EnumOptions::of(PaymentType::cases()),
            'today' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    public function update(VoucherRequest $request, Voucher $voucher): RedirectResponse
    {
        $this->vouchers->update($voucher, $request->details(), $request->lines(), $this->actor($request));
        Toast::success('messages.voucher_updated', ['number' => $voucher->voucher_number]);

        return to_route(RouteNames::VOUCHERS_SHOW, $voucher);
    }

    public function verify(Request $request, Voucher $voucher): RedirectResponse
    {
        $this->vouchers->verify($voucher, $this->actor($request));
        Toast::success('messages.voucher_verified', ['number' => $voucher->voucher_number]);

        return back();
    }

    public function approve(Request $request, Voucher $voucher): RedirectResponse
    {
        $approved = $this->vouchers->approve($voucher, $this->actor($request));
        Toast::success($approved->status === VoucherStatus::Closed ? 'messages.voucher_approved_released' : 'messages.voucher_approved', ['number' => $voucher->voucher_number]);

        return back();
    }

    public function cancel(ReasonRequest $request, Voucher $voucher): RedirectResponse
    {
        $this->vouchers->cancel($voucher, $request->reason(), $this->actor($request));
        Toast::success('messages.voucher_cancelled', ['number' => $voucher->voucher_number]);

        return back();
    }

    public function print(Request $request, Voucher $voucher, DisbursementPrintQuery $print): Response
    {
        $printed = $this->vouchers->recordPrint($voucher, $this->actor($request));

        return Inertia::render('finance/vouchers/Print', $print->voucher($printed));
    }
}
