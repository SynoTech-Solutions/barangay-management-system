<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Support\Toast;
use App\Models\Barangay;
use App\Services\Barangays\BarangaySwitcher;
use App\Support\RouteNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BarangaySwitchController extends Controller
{
    public function __construct(private readonly BarangaySwitcher $switcher) {}

    public function store(Request $request, Barangay $barangay): RedirectResponse
    {
        $this->switcher->switch($this->actor($request), $barangay, $request->session());
        Toast::success('messages.barangay_switched', ['name' => $barangay->name]);

        return to_route(RouteNames::DASHBOARD);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->switcher->switch($this->actor($request), null, $request->session());
        Toast::success('messages.barangay_cleared');

        return to_route(RouteNames::DASHBOARD);
    }
}
