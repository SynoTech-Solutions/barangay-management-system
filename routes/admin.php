<?php

use App\Enums\Permission;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BarangayProfileController;
use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\LookupController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware(['barangay'])->group(function () {
        Route::middleware('can:'.Permission::UsersView->value)->group(function () {
            Route::get('users', [UserController::class, 'index'])->name('users.index');
        });

        Route::middleware('can:'.Permission::UsersManage->value)->group(function () {
            Route::get('users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::put('users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles.update');
            Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
            Route::post('users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
            Route::put('users/{user}/password', [UserController::class, 'resetPassword'])
                ->middleware('password.confirm')
                ->name('users.password.reset');
        });

        Route::middleware('can:'.Permission::BarangayProfileManage->value)->group(function () {
            Route::get('barangay-profile', [BarangayProfileController::class, 'edit'])->name('barangay-profile.edit');
            Route::put('barangay-profile', [BarangayProfileController::class, 'update'])->name('barangay-profile.update');
            Route::post('barangay-profile/signatories/{office}', [BarangayProfileController::class, 'updateSignatory'])->name('barangay-profile.signatories.update');
        });
    });

    Route::middleware('can:'.Permission::FeaturesView->value)->group(function () {
        Route::get('features', [FeatureController::class, 'index'])->name('features.index');
        Route::put('features', [FeatureController::class, 'update'])->name('features.update');
    });

    Route::middleware('can:'.Permission::LookupsManage->value)->group(function () {
        Route::get('lookups/{type}', [LookupController::class, 'index'])->name('lookups.index');
        Route::post('lookups/{type}', [LookupController::class, 'store'])->name('lookups.store');
        Route::put('lookups/{type}/{value}', [LookupController::class, 'update'])->name('lookups.update');
        Route::post('lookups/{type}/{value}/deactivate', [LookupController::class, 'deactivate'])->name('lookups.deactivate');
        Route::post('lookups/{type}/{value}/reactivate', [LookupController::class, 'reactivate'])->name('lookups.reactivate');
    });

    Route::get('audit', AuditLogController::class)
        ->middleware('can:'.Permission::AuditView->value)
        ->name('audit.index');
});
