<?php

use App\Enums\FeatureFlag;
use App\Enums\Permission;
use App\Http\Controllers\Blotter\BlotterController;
use App\Http\Controllers\Blotter\BlotterPhotoController;
use App\Http\Controllers\Businesses\BusinessController;
use App\Http\Controllers\Businesses\BusinessSearchController;
use App\Http\Controllers\Cedula\CedulaController;
use App\Http\Controllers\Cedula\CedulaTemplateController;
use App\Http\Controllers\Certificates\BarangayClearanceController;
use App\Http\Controllers\Certificates\BusinessClearanceController;
use App\Http\Controllers\Certificates\IndigencyCertificateController;
use App\Http\Controllers\Reports\BusinessReportController;
use Illuminate\Support\Facades\Route;

$can = fn (Permission $permission): string => 'can:'.$permission->value;
$feature = fn (FeatureFlag $flag): string => 'feature:'.$flag->value;

Route::middleware(['auth', 'barangay'])->group(function () use ($can, $feature) {
    Route::middleware($feature(FeatureFlag::BarangayClearance))->prefix('clearances')->name('clearances.')->whereNumber('clearance')->group(function () use ($can, $feature) {
        Route::get('/', [BarangayClearanceController::class, 'index'])->middleware($can(Permission::ClearancesView))->name('index');

        Route::middleware($can(Permission::ClearancesIssue))->group(function () {
            Route::get('create', [BarangayClearanceController::class, 'create'])->name('create');
            Route::post('/', [BarangayClearanceController::class, 'store'])->name('store');
            Route::get('{clearance}/edit', [BarangayClearanceController::class, 'edit'])->name('edit');
            Route::put('{clearance}', [BarangayClearanceController::class, 'update'])->name('update');
            Route::get('{clearance}/print', [BarangayClearanceController::class, 'print'])->name('print');
        });

        Route::post('release', [BarangayClearanceController::class, 'release'])
            ->middleware([$can(Permission::ClearancesRelease), $feature(FeatureFlag::DocumentReleaseStep)])
            ->name('release');
        Route::post('cancel', [BarangayClearanceController::class, 'cancel'])->middleware($can(Permission::ClearancesCancel))->name('cancel');
    });

    Route::middleware($feature(FeatureFlag::BusinessRegistry))->prefix('businesses')->name('businesses.')->whereNumber('business')->group(function () use ($can) {
        Route::middleware($can(Permission::BusinessesView))->group(function () {
            Route::get('/', [BusinessController::class, 'index'])->name('index');
            Route::get('search', BusinessSearchController::class)->name('search');
            Route::get('{business}', [BusinessController::class, 'show'])->name('show');
        });

        Route::middleware($can(Permission::BusinessesManage))->group(function () {
            Route::get('create', [BusinessController::class, 'create'])->name('create');
            Route::post('/', [BusinessController::class, 'store'])->name('store');
            Route::get('{business}/edit', [BusinessController::class, 'edit'])->name('edit');
            Route::put('{business}', [BusinessController::class, 'update'])->name('update');
            Route::post('{business}/status', [BusinessController::class, 'status'])->name('status');
        });
    });

    Route::middleware($feature(FeatureFlag::BusinessClearance))->prefix('business-clearances')->name('business-clearances.')->whereNumber('businessClearance')->group(function () use ($can, $feature) {
        Route::middleware($can(Permission::ClearancesView))->group(function () {
            Route::get('/', [BusinessClearanceController::class, 'index'])->name('index');
            Route::get('expired', [BusinessClearanceController::class, 'expired'])->name('expired');
        });

        Route::middleware($can(Permission::ClearancesIssue))->group(function () {
            Route::get('create', [BusinessClearanceController::class, 'create'])->name('create');
            Route::post('/', [BusinessClearanceController::class, 'store'])->name('store');
            Route::get('{businessClearance}/edit', [BusinessClearanceController::class, 'edit'])->name('edit');
            Route::put('{businessClearance}', [BusinessClearanceController::class, 'update'])->name('update');
            Route::get('{businessClearance}/print', [BusinessClearanceController::class, 'print'])->name('print');
        });

        Route::post('release', [BusinessClearanceController::class, 'release'])
            ->middleware([$can(Permission::ClearancesRelease), $feature(FeatureFlag::DocumentReleaseStep)])
            ->name('release');
        Route::post('cancel', [BusinessClearanceController::class, 'cancel'])->middleware($can(Permission::ClearancesCancel))->name('cancel');
    });

    Route::get('reports/businesses', BusinessReportController::class)
        ->middleware([$can(Permission::ReportsView), $feature(FeatureFlag::BusinessRegistry)])
        ->name('reports.businesses');

    Route::middleware($feature(FeatureFlag::Cedula))->prefix('cedulas')->name('cedulas.')->whereNumber(['cedula', 'template'])->group(function () use ($can, $feature) {
        Route::middleware($can(Permission::CedulaView))->group(function () {
            Route::get('/', [CedulaController::class, 'index'])->name('index');
            Route::get('references', [CedulaController::class, 'references'])->name('references');
            Route::get('{cedula}', [CedulaController::class, 'show'])->name('show');
        });

        Route::middleware($can(Permission::CedulaIssue))->group(function () use ($feature) {
            Route::get('create', [CedulaController::class, 'create'])->name('create');
            Route::get('quote', [CedulaController::class, 'quote'])->name('quote');
            Route::post('/', [CedulaController::class, 'store'])->name('store');
            Route::get('{cedula}/print', [CedulaController::class, 'print'])->name('print');

            Route::middleware($feature(FeatureFlag::CedulaTemplates))->prefix('templates')->name('templates.')->group(function () {
                Route::get('/', [CedulaTemplateController::class, 'index'])->name('index');
                Route::post('/', [CedulaTemplateController::class, 'store'])->name('store');
                Route::put('{template}', [CedulaTemplateController::class, 'update'])->name('update');
                Route::post('{template}/deactivate', [CedulaTemplateController::class, 'deactivate'])->name('deactivate');
                Route::post('{template}/reactivate', [CedulaTemplateController::class, 'reactivate'])->name('reactivate');
            });
        });

        Route::post('release', [CedulaController::class, 'release'])
            ->middleware([$can(Permission::CedulaRelease), $feature(FeatureFlag::DocumentReleaseStep)])
            ->name('release');
        Route::post('cancel', [CedulaController::class, 'cancel'])->middleware($can(Permission::CedulaCancel))->name('cancel');
    });

    Route::middleware($feature(FeatureFlag::Blotter))->prefix('blotters')->name('blotters.')->whereNumber(['blotter', 'photo'])->group(function () use ($can, $feature) {
        Route::middleware($can(Permission::BlotterView))->group(function () use ($feature) {
            Route::get('/', [BlotterController::class, 'index'])->name('index');
            Route::get('{blotter}', [BlotterController::class, 'show'])->name('show');
            Route::get('{blotter}/photos/{photo}', [BlotterPhotoController::class, 'show'])
                ->middleware($feature(FeatureFlag::BlotterPhotoAttachment))
                ->name('photos.show');
        });

        Route::middleware($can(Permission::BlotterManage))->group(function () use ($feature) {
            Route::get('create', [BlotterController::class, 'create'])->name('create');
            Route::post('/', [BlotterController::class, 'store'])->name('store');
            Route::get('{blotter}/edit', [BlotterController::class, 'edit'])->name('edit');
            Route::put('{blotter}', [BlotterController::class, 'update'])->name('update');
            Route::post('{blotter}/cancel', [BlotterController::class, 'cancel'])->name('cancel');

            Route::middleware($feature(FeatureFlag::BlotterPhotoAttachment))->group(function () {
                Route::post('{blotter}/photos', [BlotterPhotoController::class, 'store'])->name('photos.store');
                Route::delete('{blotter}/photos/{photo}', [BlotterPhotoController::class, 'destroy'])->name('photos.destroy');
            });
        });

        Route::post('{blotter}/resolve', [BlotterController::class, 'resolve'])->middleware($can(Permission::BlotterResolve))->name('resolve');
    });

    Route::middleware($feature(FeatureFlag::IndigencyCertificate))->prefix('indigency-certificates')->name('indigency-certificates.')->whereNumber('certificate')->group(function () use ($can, $feature) {
        Route::get('/', [IndigencyCertificateController::class, 'index'])->middleware($can(Permission::ClearancesView))->name('index');

        Route::middleware($can(Permission::ClearancesIssue))->group(function () {
            Route::get('create', [IndigencyCertificateController::class, 'create'])->name('create');
            Route::post('/', [IndigencyCertificateController::class, 'store'])->name('store');
            Route::get('{certificate}/edit', [IndigencyCertificateController::class, 'edit'])->name('edit');
            Route::put('{certificate}', [IndigencyCertificateController::class, 'update'])->name('update');
            Route::get('{certificate}/print', [IndigencyCertificateController::class, 'print'])->name('print');
        });

        Route::post('release', [IndigencyCertificateController::class, 'release'])
            ->middleware([$can(Permission::ClearancesRelease), $feature(FeatureFlag::DocumentReleaseStep)])
            ->name('release');
        Route::post('cancel', [IndigencyCertificateController::class, 'cancel'])->middleware($can(Permission::ClearancesCancel))->name('cancel');
    });
});
