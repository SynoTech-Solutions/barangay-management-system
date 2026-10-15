<?php

use App\Enums\FeatureFlag;
use App\Enums\Permission;
use App\Http\Controllers\Central\LocationSearchController;
use App\Http\Controllers\IdCards\IdCardController;
use App\Http\Controllers\PoliticalTags\ColorCategoryController;
use App\Http\Controllers\PoliticalTags\ResidentBulkColorController;
use App\Http\Controllers\Reports\PoliticalStatusController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Residents\BlankFormController;
use App\Http\Controllers\Residents\FamilyMemberController;
use App\Http\Controllers\Residents\PurokController;
use App\Http\Controllers\Residents\QuickAddResidentController;
use App\Http\Controllers\Residents\ResidentBulkPurokController;
use App\Http\Controllers\Residents\ResidentController;
use App\Http\Controllers\Residents\ResidentEducationController;
use App\Http\Controllers\Residents\ResidentExportController;
use App\Http\Controllers\Residents\ResidentPhotoController;
use App\Http\Controllers\Residents\ResidentPrintController;
use App\Http\Controllers\Residents\ResidentSearchController;
use App\Http\Controllers\Residents\ResidentWorkHistoryController;
use Illuminate\Support\Facades\Route;

$can = fn (Permission $permission): string => 'can:'.$permission->value;
$feature = fn (FeatureFlag $flag): string => 'feature:'.$flag->value;

Route::middleware(['auth', 'barangay'])->group(function () use ($can, $feature) {
    Route::prefix('residents')->name('residents.')->whereNumber(['resident', 'familyMember', 'education', 'workHistory'])->group(function () use ($can, $feature) {
        Route::middleware($can(Permission::ResidentsView))->group(function () use ($feature) {
            Route::get('/', [ResidentController::class, 'index'])->name('index');
            Route::get('search', ResidentSearchController::class)->name('search');
            Route::get('birthplaces', [LocationSearchController::class, 'municipalities'])->name('birthplaces.search');
            Route::get('blank-form', BlankFormController::class)
                ->middleware($feature(FeatureFlag::BlankProfileForm))
                ->name('blank-form');
            Route::get('{resident}', [ResidentController::class, 'show'])->name('show');
            Route::get('{resident}/photo', [ResidentPhotoController::class, 'show'])
                ->middleware($feature(FeatureFlag::ResidentPhoto))
                ->name('photo.show');
            Route::get('{resident}/print', ResidentPrintController::class)
                ->middleware($feature(FeatureFlag::ResidentProfilePrint))
                ->name('print');
        });

        Route::middleware($can(Permission::ResidentsExport))->group(function () use ($feature) {
            Route::get('export', ResidentExportController::class)
                ->middleware($feature(FeatureFlag::GridExcelExport))
                ->name('export');
        });

        Route::middleware($can(Permission::ResidentsCreate))->group(function () use ($feature) {
            Route::get('create', [ResidentController::class, 'create'])->name('create');
            Route::post('/', [ResidentController::class, 'store'])->name('store');
            Route::post('quick-add', QuickAddResidentController::class)
                ->middleware($feature(FeatureFlag::ResidentQuickAdd))
                ->name('quick-add');
        });

        Route::middleware($can(Permission::ResidentsUpdate))->group(function () use ($feature) {
            Route::get('{resident}/edit', [ResidentController::class, 'edit'])->name('edit');
            Route::put('{resident}', [ResidentController::class, 'update'])->name('update');

            Route::middleware($feature(FeatureFlag::ResidentPhoto))->group(function () {
                Route::post('{resident}/photo', [ResidentPhotoController::class, 'update'])->name('photo.update');
                Route::delete('{resident}/photo', [ResidentPhotoController::class, 'destroy'])->name('photo.destroy');
            });

            Route::scopeBindings()->group(function () use ($feature) {
                Route::post('{resident}/family-members', [FamilyMemberController::class, 'store'])->name('family-members.store');
                Route::put('{resident}/family-members/{familyMember}', [FamilyMemberController::class, 'update'])->name('family-members.update');
                Route::delete('{resident}/family-members/{familyMember}', [FamilyMemberController::class, 'destroy'])->name('family-members.destroy');

                Route::middleware($feature(FeatureFlag::ResidentWorkEducation))->group(function () {
                    Route::post('{resident}/educations', [ResidentEducationController::class, 'store'])->name('educations.store');
                    Route::put('{resident}/educations/{education}', [ResidentEducationController::class, 'update'])->name('educations.update');
                    Route::delete('{resident}/educations/{education}', [ResidentEducationController::class, 'destroy'])->name('educations.destroy');
                    Route::post('{resident}/work-histories', [ResidentWorkHistoryController::class, 'store'])->name('work-histories.store');
                    Route::put('{resident}/work-histories/{workHistory}', [ResidentWorkHistoryController::class, 'update'])->name('work-histories.update');
                    Route::delete('{resident}/work-histories/{workHistory}', [ResidentWorkHistoryController::class, 'destroy'])->name('work-histories.destroy');
                });
            });
        });

        Route::middleware($can(Permission::ResidentsDelete))->group(function () {
            Route::delete('{resident}', [ResidentController::class, 'destroy'])->name('destroy');
            Route::post('{resident}/restore', [ResidentController::class, 'restore'])->withTrashed()->name('restore');
        });

        Route::post('bulk/purok', ResidentBulkPurokController::class)
            ->middleware([$can(Permission::ResidentsBulkEdit), $feature(FeatureFlag::ResidentBulkEdit)])
            ->name('bulk.purok');
        Route::post('bulk/color', ResidentBulkColorController::class)
            ->middleware([$can(Permission::PoliticalTagsManage), $feature(FeatureFlag::PoliticalColorTagging), $feature(FeatureFlag::ResidentBulkEdit)])
            ->name('bulk.color');
    });

    Route::middleware($can(Permission::PuroksManage))->prefix('puroks')->name('puroks.')->group(function () {
        Route::get('/', [PurokController::class, 'index'])->name('index');
        Route::post('/', [PurokController::class, 'store'])->name('store');
        Route::put('{purok}', [PurokController::class, 'update'])->name('update');
        Route::post('{purok}/deactivate', [PurokController::class, 'deactivate'])->name('deactivate');
        Route::post('{purok}/reactivate', [PurokController::class, 'reactivate'])->name('reactivate');
        Route::delete('{purok}', [PurokController::class, 'destroy'])->name('destroy');
    });

    Route::middleware($feature(FeatureFlag::ResidentIdCards))->prefix('id-cards')->name('id-cards.')->whereNumber('idCard')->group(function () use ($can, $feature) {
        Route::middleware($can(Permission::ResidentsView))->group(function () {
            Route::get('/', [IdCardController::class, 'index'])->name('index');
            Route::get('print', [IdCardController::class, 'printLedger'])->name('ledger.print');
        });

        Route::middleware($can(Permission::IdCardsIssue))->group(function () {
            Route::get('create', [IdCardController::class, 'create'])->name('create');
            Route::post('/', [IdCardController::class, 'store'])->name('store');
            Route::get('{idCard}/edit', [IdCardController::class, 'edit'])->name('edit');
            Route::put('{idCard}', [IdCardController::class, 'update'])->name('update');
            Route::get('{idCard}/print', [IdCardController::class, 'print'])->name('print');
        });

        Route::post('release', [IdCardController::class, 'release'])
            ->middleware([$can(Permission::IdCardsRelease), $feature(FeatureFlag::DocumentReleaseStep)])
            ->name('release');
        Route::post('cancel', [IdCardController::class, 'cancel'])->middleware($can(Permission::IdCardsCancel))->name('cancel');
    });

    Route::middleware([$feature(FeatureFlag::PoliticalColorTagging), $can(Permission::PoliticalTagsManage)])
        ->prefix('political-tags/categories')
        ->name('political-tags.categories.')
        ->group(function () {
            Route::get('/', [ColorCategoryController::class, 'index'])->name('index');
            Route::post('/', [ColorCategoryController::class, 'store'])->name('store');
            Route::put('{category}', [ColorCategoryController::class, 'update'])->name('update');
            Route::post('{category}/deactivate', [ColorCategoryController::class, 'deactivate'])->name('deactivate');
            Route::post('{category}/reactivate', [ColorCategoryController::class, 'reactivate'])->name('reactivate');
        });

    Route::middleware($can(Permission::ReportsView))->prefix('reports')->name('reports.')->group(function () use ($can, $feature) {
        Route::get('statistics', [ReportController::class, 'statistics'])->name('statistics');
        Route::get('residents', [ReportController::class, 'residents'])->name('residents');
        Route::get('residents/print', [ReportController::class, 'printResidents'])->name('residents.print');
        Route::get('political-status', PoliticalStatusController::class)
            ->middleware([$feature(FeatureFlag::PoliticalColorTagging), $can(Permission::PoliticalTagsView)])
            ->name('political-status');
    });
});
