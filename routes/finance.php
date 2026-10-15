<?php

use App\Enums\FeatureFlag;
use App\Enums\Permission;
use App\Http\Controllers\Finance\AccountController;
use App\Http\Controllers\Finance\BankAccountController;
use App\Http\Controllers\Finance\BudgetController;
use App\Http\Controllers\Finance\CheckController;
use App\Http\Controllers\Finance\CollectionReportController;
use App\Http\Controllers\Finance\CollectionTypeController;
use App\Http\Controllers\Finance\LiquidationController;
use App\Http\Controllers\Finance\OtherCollectionController;
use App\Http\Controllers\Finance\VendorController;
use App\Http\Controllers\Finance\VoucherController;
use Illuminate\Support\Facades\Route;

$can = fn (Permission $permission): string => 'can:'.$permission->value;
$feature = fn (FeatureFlag $flag): string => 'feature:'.$flag->value;

Route::middleware(['auth', 'barangay'])->group(function () use ($can, $feature) {
    Route::middleware($feature(FeatureFlag::Finance))->prefix('finance')->name('finance.')->group(function () use ($can, $feature) {
        Route::prefix('accounts')->name('accounts.')->whereNumber('account')->group(function () use ($can) {
            Route::get('/', [AccountController::class, 'index'])->middleware($can(Permission::BudgetView))->name('index');

            Route::middleware($can(Permission::ChartOfAccountsManage))->group(function () {
                Route::post('/', [AccountController::class, 'store'])->name('store');
                Route::put('{account}', [AccountController::class, 'update'])->name('update');
                Route::post('{account}/deactivate', [AccountController::class, 'deactivate'])->name('deactivate');
                Route::post('{account}/reactivate', [AccountController::class, 'reactivate'])->name('reactivate');
                Route::delete('{account}', [AccountController::class, 'destroy'])->name('destroy');
            });
        });

        Route::prefix('budgets')->name('budgets.')->whereNumber(['budget', 'account'])->group(function () use ($can, $feature) {
            Route::middleware($can(Permission::BudgetView))->group(function () {
                Route::get('/', [BudgetController::class, 'index'])->name('index');
                Route::get('{budget}', [BudgetController::class, 'show'])->name('show');
                Route::get('{budget}/summary', [BudgetController::class, 'summary'])->name('summary');
                Route::get('{budget}/accounts/{account}/disbursements', [BudgetController::class, 'disbursements'])->name('disbursements');
            });

            Route::middleware($can(Permission::BudgetPrepare))->group(function () use ($feature) {
                Route::post('/', [BudgetController::class, 'store'])->name('store');
                Route::put('{budget}', [BudgetController::class, 'update'])->name('update');
                Route::put('{budget}/lines', [BudgetController::class, 'lines'])->name('lines');
                Route::post('{budget}/copy', [BudgetController::class, 'copy'])->middleware($feature(FeatureFlag::BudgetCopyPrevious))->name('copy');
            });

            Route::post('{budget}/approve', [BudgetController::class, 'approve'])->middleware($can(Permission::BudgetApprove))->name('approve');
            Route::post('{budget}/revisions', [BudgetController::class, 'revise'])
                ->middleware([$can(Permission::BudgetRevise), $feature(FeatureFlag::BudgetRevision)])
                ->name('revise');
        });

        Route::prefix('bank-accounts')->name('bank-accounts.')->whereNumber('bankAccount')->group(function () use ($can) {
            Route::middleware($can(Permission::ChecksView))->group(function () {
                Route::get('/', [BankAccountController::class, 'index'])->name('index');
                Route::get('print', [BankAccountController::class, 'print'])->name('print');
            });

            Route::middleware($can(Permission::BankAccountsManage))->group(function () {
                Route::post('/', [BankAccountController::class, 'store'])->name('store');
                Route::put('{bankAccount}', [BankAccountController::class, 'update'])->name('update');
                Route::post('{bankAccount}/deactivate', [BankAccountController::class, 'deactivate'])->name('deactivate');
                Route::post('{bankAccount}/reactivate', [BankAccountController::class, 'reactivate'])->name('reactivate');
            });
        });

        Route::prefix('vouchers')->name('vouchers.')->whereNumber('voucher')->group(function () use ($can) {
            Route::middleware($can(Permission::VouchersView))->group(function () {
                Route::get('/', [VoucherController::class, 'index'])->name('index');
                Route::get('{voucher}', [VoucherController::class, 'show'])->name('show');
                Route::get('{voucher}/print', [VoucherController::class, 'print'])->name('print');
            });

            Route::middleware($can(Permission::VouchersCreate))->group(function () {
                Route::get('create', [VoucherController::class, 'create'])->name('create');
                Route::post('/', [VoucherController::class, 'store'])->name('store');
                Route::get('{voucher}/edit', [VoucherController::class, 'edit'])->name('edit');
                Route::put('{voucher}', [VoucherController::class, 'update'])->name('update');
            });

            Route::post('{voucher}/verify', [VoucherController::class, 'verify'])->middleware($can(Permission::VouchersVerify))->name('verify');
            Route::post('{voucher}/approve', [VoucherController::class, 'approve'])->middleware($can(Permission::VouchersApprove))->name('approve');
            Route::post('{voucher}/cancel', [VoucherController::class, 'cancel'])->middleware($can(Permission::VouchersCancel))->name('cancel');

            Route::middleware($can(Permission::ChecksIssue))->group(function () {
                Route::get('{voucher}/check', [CheckController::class, 'create'])->name('checks.create');
                Route::post('{voucher}/check', [CheckController::class, 'store'])->name('checks.store');
            });
        });

        Route::prefix('checks')->name('checks.')->whereNumber('check')->group(function () use ($can, $feature) {
            Route::get('/', [CheckController::class, 'index'])->middleware($can(Permission::ChecksView))->name('index');

            Route::middleware([$feature(FeatureFlag::DirectCheckIssuance), $can(Permission::ChecksIssueDirect)])->group(function () {
                Route::get('direct', [CheckController::class, 'createDirect'])->name('direct.create');
                Route::post('direct', [CheckController::class, 'storeDirect'])->name('direct.store');
            });

            Route::middleware($can(Permission::ChecksIssue))->group(function () {
                Route::get('{check}/edit', [CheckController::class, 'edit'])->name('edit');
                Route::put('{check}', [CheckController::class, 'update'])->name('update');
            });

            Route::post('{check}/cancel', [CheckController::class, 'cancel'])->middleware($can(Permission::ChecksCancel))->name('cancel');
            Route::post('{check}/release', [CheckController::class, 'release'])->middleware($can(Permission::ChecksRelease))->name('release');
            Route::get('{check}/print', [CheckController::class, 'print'])
                ->middleware([$feature(FeatureFlag::CheckPrinting), $can(Permission::ChecksPrint)])
                ->name('print');
        });

        Route::middleware($feature(FeatureFlag::CashAdvanceLiquidation))->prefix('liquidations')->name('liquidations.')->whereNumber(['liquidation', 'voucher'])->group(function () use ($can, $feature) {
            Route::middleware($can(Permission::VouchersView))->group(function () {
                Route::get('/', [LiquidationController::class, 'index'])->name('index');
                Route::get('{liquidation}/print', [LiquidationController::class, 'print'])->name('print');
            });

            Route::middleware($can(Permission::LiquidationsManage))->group(function () {
                Route::get('vouchers/{voucher}', [LiquidationController::class, 'create'])->name('create');
                Route::post('vouchers/{voucher}', [LiquidationController::class, 'store'])->name('store');
                Route::get('{liquidation}/edit', [LiquidationController::class, 'edit'])->name('edit');
                Route::put('{liquidation}', [LiquidationController::class, 'update'])->name('update');
            });

            Route::post('{liquidation}/clear', [LiquidationController::class, 'clear'])
                ->middleware([$feature(FeatureFlag::LiquidationClearing), $can(Permission::LiquidationsClear)])
                ->name('clear');
        });

        Route::prefix('vendors')->name('vendors.')->whereNumber('vendor')->group(function () use ($can) {
            Route::middleware($can(Permission::VouchersView))->group(function () {
                Route::get('/', [VendorController::class, 'index'])->name('index');
                Route::get('print', [VendorController::class, 'print'])->name('print');
            });

            Route::middleware($can(Permission::SuppliersManage))->group(function () {
                Route::post('/', [VendorController::class, 'store'])->name('store');
                Route::put('{vendor}', [VendorController::class, 'update'])->name('update');
                Route::post('{vendor}/deactivate', [VendorController::class, 'deactivate'])->name('deactivate');
                Route::post('{vendor}/reactivate', [VendorController::class, 'reactivate'])->name('reactivate');
            });
        });
    });

    Route::prefix('collections')->name('collections.')->whereNumber(['collection', 'collectionType'])->group(function () use ($can, $feature) {
        Route::middleware([$feature(FeatureFlag::ConsolidatedCollectionReport), $can(Permission::CollectionsView)])->prefix('report')->name('report.')->group(function () use ($can, $feature) {
            Route::get('/', [CollectionReportController::class, 'index'])->name('index');
            Route::get('print', [CollectionReportController::class, 'print'])->name('print');
            Route::get('export', [CollectionReportController::class, 'export'])
                ->middleware([$can(Permission::CollectionsExport), $feature(FeatureFlag::GridExcelExport)])
                ->name('export');
        });

        Route::middleware($feature(FeatureFlag::OtherCollections))->group(function () use ($can) {
            Route::middleware($can(Permission::CollectionsView))->group(function () {
                Route::get('/', [OtherCollectionController::class, 'index'])->name('index');
                Route::get('print', [OtherCollectionController::class, 'print'])->name('print');
            });

            Route::middleware($can(Permission::CollectionsCreate))->group(function () {
                Route::get('create', [OtherCollectionController::class, 'create'])->name('create');
                Route::post('/', [OtherCollectionController::class, 'store'])->name('store');
                Route::get('{collection}/edit', [OtherCollectionController::class, 'edit'])->name('edit');
                Route::put('{collection}', [OtherCollectionController::class, 'update'])->name('update');
            });

            Route::post('{collection}/cancel', [OtherCollectionController::class, 'cancel'])->middleware($can(Permission::CollectionsCancel))->name('cancel');

            Route::middleware($can(Permission::CollectionTypesManage))->prefix('types')->name('types.')->group(function () {
                Route::get('/', [CollectionTypeController::class, 'index'])->name('index');
                Route::post('/', [CollectionTypeController::class, 'store'])->name('store');
                Route::put('{collectionType}', [CollectionTypeController::class, 'update'])->name('update');
                Route::post('{collectionType}/deactivate', [CollectionTypeController::class, 'deactivate'])->name('deactivate');
                Route::post('{collectionType}/reactivate', [CollectionTypeController::class, 'reactivate'])->name('reactivate');
            });
        });
    });
});
