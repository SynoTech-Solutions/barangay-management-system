<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationSearchController extends Controller
{
    private const int RESULT_LIMIT = 20;

    public function municipalities(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();

        $results = Municipality::query()
            ->with('province:id,name')
            ->when($search !== '', fn ($query) => $query->whereRaw('name ilike ?', [Like::contains($search)]))
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn (Municipality $municipality): array => [
                'id' => $municipality->id,
                'label' => $municipality->province === null
                    ? $municipality->name
                    : "{$municipality->name}, {$municipality->province->name}",
            ]);

        return response()->json(['data' => $results]);
    }

    public function barangays(Request $request): JsonResponse
    {
        $search = $request->string('search')->toString();

        $results = Barangay::query()
            ->with('municipality:id,name')
            ->when($search !== '', fn ($query) => $query->whereRaw('name ilike ?', [Like::contains($search)]))
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(fn (Barangay $barangay): array => [
                'id' => $barangay->id,
                'label' => "{$barangay->name}, {$barangay->municipality->name}",
                'status' => $barangay->status->value,
            ]);

        return response()->json(['data' => $results]);
    }
}
