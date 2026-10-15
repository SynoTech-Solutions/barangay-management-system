<?php

namespace App\Http\Controllers\Residents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Residents\QuickAddResidentRequest;
use App\Http\Resources\Residents\ResidentOptionResource;
use App\Http\Support\Toast;
use App\Services\Residents\ResidentRegistry;
use App\Support\RouteNames;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class QuickAddResidentController extends Controller
{
    public function __invoke(QuickAddResidentRequest $request, ResidentRegistry $registry): JsonResponse|RedirectResponse
    {
        $resident = $registry->quickAdd($request->profile(), $this->actor($request));

        if ($request->expectsJson()) {
            return response()->json(['data' => (new ResidentOptionResource($resident))->resolve()], Response::HTTP_CREATED);
        }

        Toast::success('messages.resident_created', ['number' => $resident->resident_number]);

        return to_route(RouteNames::RESIDENTS_EDIT, $resident);
    }
}
