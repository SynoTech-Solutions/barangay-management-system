<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Support\RouteNames;
use Illuminate\Support\Facades\Route;

Route::get('health/ready', [HealthController::class, 'ready'])->name('health.ready');

Route::get('/', fn () => to_route(RouteNames::DASHBOARD))->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name(RouteNames::DASHBOARD);
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/residents.php';
require __DIR__.'/certificates.php';
require __DIR__.'/finance.php';
require __DIR__.'/forms.php';
require __DIR__.'/sms.php';
require __DIR__.'/central.php';
