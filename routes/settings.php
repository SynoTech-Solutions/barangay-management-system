<?php

use App\Enums\FeatureFlag;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Support\RateLimiters;
use App\Support\RouteNames;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('settings', fn () => to_route(RouteNames::PROFILE_EDIT));

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])
        ->middleware('feature:'.FeatureFlag::SelfServiceProfile->value)
        ->name('profile.update');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware(['throttle:'.RateLimiters::PASSWORD_UPDATE, 'feature:'.FeatureFlag::SelfServiceProfile->value])
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')
        ->middleware('feature:'.FeatureFlag::UserTheme->value)
        ->name('appearance.edit');
});
