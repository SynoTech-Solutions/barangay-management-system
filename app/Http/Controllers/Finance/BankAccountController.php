<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\BankAccountRequest;
use App\Http\Requests\Finance\SearchRequest;
use App\Http\Resources\Finance\BankAccountResource;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\BankAccount;
use App\Queries\BankAccountListQuery;
use App\Queries\DocumentPrintContext;
use App\Services\Finance\BankAccountService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function __construct(private readonly BankAccountService $accounts) {}

    public function index(SearchRequest $request, BankAccountListQuery $query): Response
    {
        return Inertia::render('finance/bank-accounts/Index', [
            'accounts' => Paginated::from($query->paginate($request->perPage()), BankAccountResource::class),
        ]);
    }

    public function print(BankAccountListQuery $query, DocumentPrintContext $context): Response
    {
        return Inertia::render('finance/bank-accounts/ListPrint', [
            'header' => $context->header(),
            'accounts' => array_values(BankAccountResource::collection($query->forPrint())->resolve()),
            'limit' => BankAccountListQuery::PRINT_LIMIT,
            'printedAt' => CarbonImmutable::now()->toIso8601String(),
        ]);
    }

    public function store(BankAccountRequest $request): RedirectResponse
    {
        $this->accounts->save(null, $request->details(), $this->actor($request));
        Toast::success('messages.bank_account_saved');

        return back();
    }

    public function update(BankAccountRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->accounts->save($bankAccount, $request->details(), $this->actor($request));
        Toast::success('messages.bank_account_saved');

        return back();
    }

    public function deactivate(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->accounts->setActive($bankAccount, false, $this->actor($request));
        Toast::success('messages.record_deactivated');

        return back();
    }

    public function reactivate(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->accounts->setActive($bankAccount, true, $this->actor($request));
        Toast::success('messages.record_reactivated');

        return back();
    }
}
