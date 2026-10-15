<?php

namespace App\Http\Controllers;

use App\Queries\DashboardCounters;
use App\Tenancy\CurrentBarangay;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CurrentBarangay $currentBarangay, DashboardCounters $counters): Response
    {
        return Inertia::render('Dashboard', [
            'counters' => $currentBarangay->get() === null ? [] : $counters->for($this->actor($request), CarbonImmutable::now()),
        ]);
    }
}
