<?php

namespace App\Http\Controllers\Finance;

use App\Enums\AccountGroupType;
use App\Enums\AccountKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\AccountRequest;
use App\Http\Requests\Finance\AccountUpdateRequest;
use App\Http\Resources\Finance\AccountResource;
use App\Http\Support\EnumOptions;
use App\Http\Support\Toast;
use App\Models\Account;
use App\Queries\ChartOfAccountsQuery;
use App\Services\Finance\ChartOfAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(private readonly ChartOfAccounts $chart) {}

    public function index(Request $request, ChartOfAccountsQuery $accounts): Response
    {
        return Inertia::render('finance/accounts/Index', [
            'accounts' => array_values(AccountResource::collection($accounts->tree())->resolve()),
            'kinds' => EnumOptions::of(AccountKind::cases()),
            'groupTypes' => EnumOptions::of(AccountGroupType::cases()),
            'canEditLocked' => $this->chart->canEditLocked($this->actor($request)),
        ]);
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        $account = $this->chart->create($request->details(), $this->actor($request));
        Toast::success('messages.account_saved', ['code' => $account->code]);

        return back();
    }

    public function update(AccountUpdateRequest $request, Account $account): RedirectResponse
    {
        $this->chart->update($account, $request->details(), $this->actor($request));
        Toast::success('messages.account_saved', ['code' => $account->code]);

        return back();
    }

    public function deactivate(Request $request, Account $account): RedirectResponse
    {
        $this->chart->setActive($account, false, $this->actor($request));
        Toast::success('messages.account_deactivated', ['code' => $account->code]);

        return back();
    }

    public function reactivate(Request $request, Account $account): RedirectResponse
    {
        $this->chart->setActive($account, true, $this->actor($request));
        Toast::success('messages.account_reactivated', ['code' => $account->code]);

        return back();
    }

    public function destroy(Request $request, Account $account): RedirectResponse
    {
        $deleted = $this->chart->remove($account, $this->actor($request));
        Toast::success($deleted ? 'messages.account_deleted' : 'messages.account_deactivated_in_use', ['code' => $account->code]);

        return back();
    }
}
