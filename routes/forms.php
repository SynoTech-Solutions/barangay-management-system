<?php

use App\Enums\FeatureFlag;
use App\Enums\Permission;
use App\Http\Controllers\Forms\FormPicklistController;
use App\Http\Controllers\Forms\FormTemplateController;
use App\Http\Controllers\Forms\FormTemplatePreviewController;
use App\Http\Controllers\Forms\FormTokenController;
use App\Http\Controllers\Forms\FormTransactionController;
use App\Http\Controllers\Forms\FormTransactionReportController;
use App\Http\Controllers\Reports\SavedReportController;
use Illuminate\Support\Facades\Route;

$can = fn (Permission $permission): string => 'can:'.$permission->value;
$feature = fn (FeatureFlag $flag): string => 'feature:'.$flag->value;

Route::middleware(['auth', 'barangay'])->group(function () use ($can, $feature) {
    Route::middleware($feature(FeatureFlag::FormsEngine))->prefix('forms')->name('forms.')->group(function () use ($can, $feature) {
        Route::middleware($can(Permission::FormTemplatesManage))->group(function () use ($feature) {
            Route::prefix('templates')->name('templates.')->whereNumber(['template', 'version'])->group(function () use ($feature) {
                Route::get('/', [FormTemplateController::class, 'index'])->name('index');
                Route::get('create', [FormTemplateController::class, 'create'])->name('create');
                Route::post('/', [FormTemplateController::class, 'store'])->name('store');
                Route::post('preview', [FormTemplatePreviewController::class, 'draft'])
                    ->middleware($feature(FeatureFlag::FormsRichTextTemplates))
                    ->name('preview');
                Route::post('inspect-pdf', [FormTemplatePreviewController::class, 'inspect'])
                    ->middleware($feature(FeatureFlag::PdfFormTemplates))
                    ->name('inspect-pdf');
                Route::get('{template}', [FormTemplateController::class, 'show'])->name('show');
                Route::get('{template}/edit', [FormTemplateController::class, 'edit'])->name('edit');
                Route::put('{template}', [FormTemplateController::class, 'update'])->name('update');
                Route::post('{template}/deactivate', [FormTemplateController::class, 'deactivate'])->name('deactivate');
                Route::post('{template}/reactivate', [FormTemplateController::class, 'reactivate'])->name('reactivate');
                Route::get('{template}/versions/{version}/preview', [FormTemplatePreviewController::class, 'show'])->name('versions.preview');
                Route::get('{template}/versions/{version}/source', [FormTemplatePreviewController::class, 'source'])
                    ->middleware($feature(FeatureFlag::PdfFormTemplates))
                    ->name('versions.source');
            });

            Route::get('tokens', FormTokenController::class)->name('tokens');

            Route::middleware($feature(FeatureFlag::FormsTablePicklists))->prefix('picklists')->name('picklists.')->whereNumber(['picklist', 'item'])->group(function () {
                Route::get('/', [FormPicklistController::class, 'index'])->name('index');
                Route::post('/', [FormPicklistController::class, 'store'])->name('store');
                Route::put('{picklist}', [FormPicklistController::class, 'update'])->name('update');
                Route::post('{picklist}/items', [FormPicklistController::class, 'storeItem'])->name('items.store');
                Route::put('{picklist}/items/{item}', [FormPicklistController::class, 'updateItem'])->name('items.update');
            });
        });

        Route::prefix('transactions')->name('transactions.')->whereNumber('transaction')->group(function () use ($can, $feature) {
            Route::middleware($can(Permission::FormTransactionsView))->group(function () use ($feature) {
                Route::get('/', [FormTransactionController::class, 'index'])->name('index');
                Route::get('export', [FormTransactionReportController::class, 'export'])->middleware($feature(FeatureFlag::GridExcelExport))->name('export');
                Route::get('list-print', [FormTransactionReportController::class, 'print'])->middleware($feature(FeatureFlag::GridPrint))->name('list-print');
            });

            Route::middleware($can(Permission::FormTransactionsCreate))->group(function () {
                Route::get('create', [FormTransactionController::class, 'create'])->name('create');
                Route::post('/', [FormTransactionController::class, 'store'])->name('store');
                Route::get('{transaction}/edit', [FormTransactionController::class, 'edit'])->name('edit');
                Route::put('{transaction}', [FormTransactionController::class, 'update'])->name('update');
                Route::get('{transaction}/print', [FormTransactionController::class, 'print'])->name('print');
            });

            Route::post('release', [FormTransactionController::class, 'release'])
                ->middleware([$can(Permission::FormTransactionsRelease), $feature(FeatureFlag::DocumentReleaseStep)])
                ->name('release');
            Route::post('cancel', [FormTransactionController::class, 'cancel'])->middleware($can(Permission::FormTransactionsCancel))->name('cancel');
        });
    });

    Route::middleware($feature(FeatureFlag::SqlReportBuilder))->prefix('reports/saved')->name('reports.saved.')->whereNumber('report')->group(function () use ($can, $feature) {
        Route::middleware($can(Permission::ReportsView))->group(function () use ($feature) {
            Route::get('/', [SavedReportController::class, 'index'])->name('index');
            Route::get('{report}', [SavedReportController::class, 'show'])->name('show');
            Route::get('{report}/export', [SavedReportController::class, 'export'])->middleware($feature(FeatureFlag::GridExcelExport))->name('export');
        });

        Route::middleware($can(Permission::ReportsDesign))->group(function () {
            Route::get('create', [SavedReportController::class, 'create'])->name('create');
            Route::post('/', [SavedReportController::class, 'store'])->name('store');
            Route::get('{report}/edit', [SavedReportController::class, 'edit'])->name('edit');
            Route::put('{report}', [SavedReportController::class, 'update'])->name('update');
            Route::post('{report}/deactivate', [SavedReportController::class, 'deactivate'])->name('deactivate');
        });
    });
});
