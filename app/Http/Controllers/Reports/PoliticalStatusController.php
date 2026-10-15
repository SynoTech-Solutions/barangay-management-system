<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Queries\PoliticalTagQuery;
use App\Queries\ResidentFormOptionsQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PoliticalStatusController extends Controller
{
    public function __invoke(Request $request, PoliticalTagQuery $tags, ResidentFormOptionsQuery $options): Response
    {
        $purokId = $request->filled('purok_id') ? $request->integer('purok_id') : null;

        return Inertia::render('reports/PoliticalStatus', [
            'purokId' => $purokId,
            'puroks' => $options->for(null)['puroks'],
            'byColor' => $tags->votersByColor($purokId),
            'byPurok' => $tags->votersByPurok(),
        ]);
    }
}
