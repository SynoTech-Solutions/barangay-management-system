<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Money\Centavos;
use App\Enums\FeatureFlag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\BudgetCopyRequest;
use App\Http\Requests\Finance\BudgetLinesRequest;
use App\Http\Requests\Finance\BudgetRequest;
use App\Http\Requests\Finance\SearchRequest;
use App\Http\Resources\Finance\BudgetResource;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\Account;
use App\Models\Budget;
use App\Queries\BudgetListQuery;
use App\Queries\BudgetSummaryQuery;
use App\Queries\BudgetWorksheetQuery;
use App\Services\Features\FeatureFlags;
use App\Services\Finance\BudgetService;
use App\Services\Finance\FundConsumption;
use App\Support\RouteNames;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $budgets) {}

    public function index(SearchRequest $request, BudgetListQuery $query): Response
    {
        return Inertia::render('finance/budgets/Index', [
            'budgets' => Paginated::from($query->paginate($request->perPage()), BudgetResource::class),
            'suggestedYear' => CarbonImmutable::now()->year,
            'yearRange' => ['min' => BudgetRequest::YEAR_MIN, 'max' => BudgetRequest::YEAR_MAX],
        ]);
    }

    public function store(BudgetRequest $request): RedirectResponse
    {
        $budget = $this->budgets->create($request->fiscalYear(), $request->title(), $this->actor($request));
        Toast::success('messages.budget_created', ['year' => $budget->fiscal_year]);

        return to_route(RouteNames::BUDGETS_SHOW, $budget);
    }

    public function update(BudgetRequest $request, Budget $budget): RedirectResponse
    {
        $this->budgets->rename($budget, $request->title(), $this->actor($request));
        Toast::success('messages.budget_updated', ['year' => $budget->fiscal_year]);

        return back();
    }

    public function show(Budget $budget, BudgetWorksheetQuery $worksheet, BudgetListQuery $list, FeatureFlags $features): Response
    {
        $copyEnabled = $features->enabled(FeatureFlag::BudgetCopyPrevious) && $budget->isDraft();

        return Inertia::render('finance/budgets/Show', [
            ...$worksheet->for($budget, $features->enabled(FeatureFlag::BudgetUnappropriatedRow)),
            'copySources' => $copyEnabled ? $list->copySources($budget)->map(fn (Budget $source): array => ['value' => $source->id, 'label' => "{$source->fiscal_year} · {$source->title}"])->values()->all() : [],
        ]);
    }

    public function lines(BudgetLinesRequest $request, Budget $budget): RedirectResponse
    {
        $this->budgets->prepare($budget, $request->amounts(), $this->actor($request));
        Toast::success('messages.budget_prepared', ['year' => $budget->fiscal_year]);

        return back();
    }

    public function copy(BudgetCopyRequest $request, Budget $budget): RedirectResponse
    {
        $this->budgets->copyFrom($budget, $request->sourceBudgetId(), $this->actor($request));
        Toast::success('messages.budget_copied', ['year' => $budget->fiscal_year]);

        return back();
    }

    public function approve(Request $request, Budget $budget): RedirectResponse
    {
        $this->budgets->approve($budget, $this->actor($request));
        Toast::success('messages.budget_approved', ['year' => $budget->fiscal_year]);

        return back();
    }

    public function revise(BudgetLinesRequest $request, Budget $budget): RedirectResponse
    {
        $version = $this->budgets->revise($budget, $request->revisedLines(), $request->remarks(), $this->actor($request));
        Toast::success('messages.budget_revised', ['year' => $budget->fiscal_year, 'version' => $version->number]);

        return back();
    }

    public function summary(Budget $budget, BudgetSummaryQuery $summary, BudgetWorksheetQuery $worksheet): Response
    {
        return Inertia::render('finance/budgets/Summary', [
            'budget' => $worksheet->budget($budget),
            'asOf' => CarbonImmutable::today()->toDateString(),
            ...$summary->for($budget),
        ]);
    }

    public function disbursements(Budget $budget, Account $account, FundConsumption $consumption, BudgetWorksheetQuery $worksheet): Response
    {
        $records = $consumption->disbursements($budget, $account);

        return Inertia::render('finance/budgets/Disbursements', [
            'budget' => $worksheet->budget($budget),
            'account' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name],
            'records' => array_map(fn ($record): array => [
                'voucher_number' => $record->voucherNumber,
                'date' => $record->date,
                'payee' => $record->payee,
                'particulars' => $record->particulars,
                'amount' => Centavos::toDecimal($record->amount),
                'check_number' => $record->checkNumber,
                'liquidation_number' => $record->liquidationNumber,
            ], $records),
            'total' => Centavos::toDecimal(array_sum(array_map(fn ($record): int => $record->amount, $records))),
        ]);
    }
}
