<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FeatureOverrideState;
use App\Enums\FeatureScope;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FeatureOverrideRequest;
use App\Http\Resources\FeatureStateResource;
use App\Http\Support\Toast;
use App\Services\Features\FeatureFlags;
use App\Tenancy\CurrentBarangay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FeatureController extends Controller
{
    public function __construct(
        private readonly FeatureFlags $features,
        private readonly CurrentBarangay $currentBarangay,
    ) {}

    public function index(): Response
    {
        $barangay = $this->currentBarangay->get();

        return Inertia::render('admin/features/Index', [
            'scope' => $barangay === null ? FeatureScope::Global->value : FeatureScope::Barangay->value,
            'features' => array_values(array_map(
                fn ($state): array => (new FeatureStateResource($state))->resolve(),
                $this->features->all($barangay),
            )),
            'canManageGlobal' => Gate::allows(Permission::FeaturesManageGlobal->value),
        ]);
    }

    public function update(FeatureOverrideRequest $request): RedirectResponse
    {
        $global = $request->scope() === FeatureScope::Global;

        Gate::authorize($global ? Permission::FeaturesManageGlobal->value : Permission::FeaturesManage->value);

        $barangay = $global ? null : $this->currentBarangay->require();
        $actorId = $this->actor($request)->id;

        if ($request->state() === FeatureOverrideState::Default) {
            $this->features->clearOverride($request->flag(), $barangay, $actorId);
            Toast::success('messages.feature_reset');
        } else {
            $this->features->override($request->flag(), $barangay, $request->state() === FeatureOverrideState::On, $request->reason(), $actorId);
            Toast::success('messages.feature_updated');
        }

        return back();
    }
}
