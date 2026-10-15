<?php

namespace App\Http\Controllers\Finance;

use App\Enums\LiquidationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\LiquidationListRequest;
use App\Http\Requests\Finance\LiquidationRequest;
use App\Http\Resources\Finance\LiquidationResource;
use App\Http\Resources\Finance\VoucherResource;
use App\Http\Support\EnumOptions;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\Liquidation;
use App\Models\Voucher;
use App\Queries\DisbursementPrintQuery;
use App\Queries\LiquidationListQuery;
use App\Queries\VoucherListQuery;
use App\Services\Finance\LiquidationService;
use App\Support\RouteNames;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiquidationController extends Controller
{
    public function __construct(private readonly LiquidationService $liquidations) {}

    public function index(LiquidationListRequest $request, LiquidationListQuery $query, VoucherListQuery $vouchers): Response
    {
        $filters = $request->filters();

        return Inertia::render('finance/liquidations/Index', [
            'unliquidated' => Paginated::from($vouchers->unliquidated($filters->search, $request->perPage()), VoucherResource::class),
            'liquidations' => Paginated::from($query->paginate($filters, $request->perPage()), LiquidationResource::class),
            'filters' => $filters->toArray(),
            'statuses' => EnumOptions::of(LiquidationStatus::cases()),
        ]);
    }

    public function create(Voucher $voucher, VoucherListQuery $vouchers): Response
    {
        return Inertia::render('finance/liquidations/Form', [
            'liquidation' => null,
            'voucher' => (new VoucherResource($vouchers->detail($voucher->id)))->resolve(),
            'today' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    public function store(LiquidationRequest $request, Voucher $voucher): RedirectResponse
    {
        $liquidation = $this->liquidations->liquidate($voucher, $request->details(), $request->actuals(), $this->actor($request));
        Toast::success('messages.liquidation_saved', ['number' => $liquidation->liquidation_number]);

        return to_route(RouteNames::LIQUIDATIONS_INDEX);
    }

    public function edit(Liquidation $liquidation, LiquidationListQuery $query, VoucherListQuery $vouchers): Response
    {
        return Inertia::render('finance/liquidations/Form', [
            'liquidation' => (new LiquidationResource($query->detail($liquidation->id)))->resolve(),
            'voucher' => (new VoucherResource($vouchers->detail($liquidation->voucher_id)))->resolve(),
            'today' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    public function update(LiquidationRequest $request, Liquidation $liquidation): RedirectResponse
    {
        $this->liquidations->update($liquidation, $request->details(), $request->actuals(), $this->actor($request));
        Toast::success('messages.liquidation_saved', ['number' => $liquidation->liquidation_number]);

        return to_route(RouteNames::LIQUIDATIONS_INDEX);
    }

    public function clear(Request $request, Liquidation $liquidation): RedirectResponse
    {
        $this->liquidations->clear($liquidation, $this->actor($request));
        Toast::success('messages.liquidation_cleared', ['number' => $liquidation->liquidation_number]);

        return back();
    }

    public function print(Request $request, Liquidation $liquidation, DisbursementPrintQuery $print): Response
    {
        $printed = $this->liquidations->recordPrint($liquidation, $this->actor($request));

        return Inertia::render('finance/liquidations/Print', $print->liquidation($printed));
    }
}
