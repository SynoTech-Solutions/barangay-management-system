<?php

use App\Enums\Permission;
use App\Http\Controllers\Central\BarangayController;
use App\Http\Controllers\Central\BarangaySwitchController;
use App\Http\Controllers\Central\LocationSearchController;
use App\Http\Controllers\Central\StaffController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('central')->name('central.')->group(function () {
    Route::middleware('can:'.Permission::BarangaysManage->value)->group(function () {
        Route::get('barangays', [BarangayController::class, 'index'])->name('barangays.index');
        Route::get('barangays/create', [BarangayController::class, 'create'])->name('barangays.create');
        Route::post('barangays', [BarangayController::class, 'store'])->name('barangays.store');
        Route::put('barangays/{barangay}/status', [BarangayController::class, 'updateStatus'])
            ->middleware('password.confirm')
            ->name('barangays.status.update');
        Route::get('municipalities/search', [LocationSearchController::class, 'municipalities'])->name('municipalities.search');
        Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
    });

    Route::middleware('can:'.Permission::BarangaysSwitch->value)->group(function () {
        Route::get('barangays/search', [LocationSearchController::class, 'barangays'])->name('barangays.search');
        Route::post('switch/{barangay}', [BarangaySwitchController::class, 'store'])->name('switch.store');
        Route::delete('switch', [BarangaySwitchController::class, 'destroy'])->name('switch.destroy');
    });
});
