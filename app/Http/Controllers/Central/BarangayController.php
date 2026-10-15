<?php

namespace App\Http\Controllers\Central;

use App\Enums\BarangayStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\OnboardBarangayRequest;
use App\Http\Resources\BarangayResource;
use App\Http\Support\Paginated;
use App\Http\Support\Toast;
use App\Models\Barangay;
use App\Services\Barangays\BarangayOnboarding;
use App\Support\Config\BmsSettings;
use App\Support\Like;
use App\Support\RouteNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BarangayController extends Controller
{
    public function __construct(private readonly BarangayOnboarding $onboarding) {}

    public function index(Request $request, BmsSettings $settings): Response
    {
        $search = $request->string('search')->toString();

        $barangays = Barangay::query()
            ->with('municipality')
            ->when($search !== '', fn ($query) => $query->whereRaw('name ilike ?', [Like::contains($search)]))
            ->orderBy('name')
            ->paginate($settings->pagination->clamp($request->integer('per_page')))
            ->withQueryString();

        return Inertia::render('central/barangays/Index', [
            'barangays' => Paginated::from($barangays, BarangayResource::class),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('central/barangays/Create');
    }

    public function store(OnboardBarangayRequest $request): RedirectResponse
    {
        $this->onboarding->onboard($request->barangay(), $request->firstAdmin(), $this->actor($request));
        Toast::success('messages.barangay_onboarded');

        return to_route(RouteNames::CENTRAL_BARANGAYS_INDEX);
    }

    public function updateStatus(Request $request, Barangay $barangay): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(BarangayStatus::class)]]);

        $this->onboarding->setStatus($barangay, BarangayStatus::from($validated['status']), $this->actor($request));
        Toast::success('messages.barangay_status_updated');

        return back();
    }
}
