<?php

namespace App\Http\Controllers\Businesses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Businesses\BusinessSearchRequest;
use App\Http\Resources\Businesses\BusinessOptionResource;
use App\Http\Support\Paginated;
use App\Queries\BusinessPickerQuery;
use Illuminate\Http\JsonResponse;

class BusinessSearchController extends Controller
{
    public function __invoke(BusinessSearchRequest $request, BusinessPickerQuery $query): JsonResponse
    {
        return response()->json(Paginated::from($query->search($request->term(), $request->perPage()), BusinessOptionResource::class));
    }
}
