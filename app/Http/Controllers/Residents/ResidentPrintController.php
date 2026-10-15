<?php

namespace App\Http\Controllers\Residents;

use App\Http\Controllers\Controller;
use App\Models\Resident;
use App\Queries\ResidentProfileQuery;
use App\Tenancy\CurrentBarangay;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResidentPrintController extends Controller
{
    public function __invoke(Request $request, Resident $resident, ResidentProfileQuery $profile, CurrentBarangay $currentBarangay): Response
    {
        $barangay = $currentBarangay->require()->load('municipality.province');
        $details = $profile->details($resident, false);

        return Inertia::render('residents/Print', [
            'resident' => $details['resident'],
            'familyMembers' => $details['family_members'],
            'header' => [
                'barangay' => $barangay->name,
                'municipality' => $barangay->municipality->name,
                'province' => $barangay->municipality->province?->name,
            ],
            'preparedBy' => $this->actor($request)->name,
            'printedAt' => CarbonImmutable::now()->toIso8601String(),
        ]);
    }
}
