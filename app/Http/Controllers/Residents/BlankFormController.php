<?php

namespace App\Http\Controllers\Residents;

use App\Enums\FeatureFlag;
use App\Enums\LookupType;
use App\Http\Controllers\Controller;
use App\Models\BarangayProfile;
use App\Queries\ResidentFormOptionsQuery;
use App\Services\Features\FeatureFlags;
use App\Tenancy\CurrentBarangay;
use Inertia\Inertia;
use Inertia\Response;

class BlankFormController extends Controller
{
    public function __invoke(CurrentBarangay $currentBarangay, FeatureFlags $features, ResidentFormOptionsQuery $options): Response
    {
        $barangay = $currentBarangay->require()->load('municipality.province');
        $sectors = $features->enabled(FeatureFlag::SectorTagging);

        return Inertia::render('residents/BlankFormPrint', [
            'header' => [
                'barangay' => $barangay->name,
                'municipality' => $barangay->municipality->name,
                'province' => $barangay->municipality->province?->name,
                'telephone' => BarangayProfile::query()->find($barangay->id)?->telephone,
            ],
            'sections' => [
                'sectors' => $sectors,
                'work_education' => $features->enabled(FeatureFlag::ResidentWorkEducation),
            ],
            'sectors' => $sectors ? $options->for(null)['lookups'][LookupType::Sector->value] ?? [] : [],
        ]);
    }
}
