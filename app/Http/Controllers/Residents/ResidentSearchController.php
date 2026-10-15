<?php

namespace App\Http\Controllers\Residents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Residents\ResidentSearchRequest;
use App\Queries\ResidentPickerQuery;
use Illuminate\Http\JsonResponse;

class ResidentSearchController extends Controller
{
    public function __invoke(ResidentSearchRequest $request, ResidentPickerQuery $query): JsonResponse
    {
        $results = $query->search($request->nameQuery(), $request->includeDeceased(), $request->excludeId(), $request->perPage(), $request->withMobile());

        return response()->json($query->payload($results, $request->forIssuance()));
    }
}
