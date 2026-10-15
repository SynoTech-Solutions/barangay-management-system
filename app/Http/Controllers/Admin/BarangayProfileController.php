<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClearanceValidityMode;
use App\Enums\SignatoryOffice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BarangayProfileRequest;
use App\Http\Requests\Admin\SignatoryRequest;
use App\Http\Resources\SignatoryResource;
use App\Http\Support\Toast;
use App\Services\Barangays\BarangayProfileService;
use App\Tenancy\CurrentBarangay;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BarangayProfileController extends Controller
{
    public function __construct(
        private readonly BarangayProfileService $profiles,
        private readonly CurrentBarangay $currentBarangay,
    ) {}

    public function edit(): Response
    {
        $barangay = $this->currentBarangay->require();
        $profile = $this->profiles->profile($barangay);
        $signatories = $this->profiles->signatories()->keyBy(fn ($signatory) => $signatory->office->value);

        return Inertia::render('admin/barangay/Profile', [
            'profile' => [
                'telephone' => $profile->telephone,
                'email' => $profile->email,
                'id_card_ordinance_no' => $profile->id_card_ordinance_no,
                'clearance_validity_mode' => $profile->clearance_validity_mode->value,
                'clearance_validity_months' => $profile->clearance_validity_months,
            ],
            'validityModes' => array_map(fn (ClearanceValidityMode $mode): string => $mode->value, ClearanceValidityMode::cases()),
            'signatories' => array_map(
                fn (SignatoryOffice $office): array => (new SignatoryResource(['office' => $office, 'signatory' => $signatories->get($office->value)]))->resolve(),
                SignatoryOffice::cases(),
            ),
        ]);
    }

    public function update(BarangayProfileRequest $request): RedirectResponse
    {
        $this->profiles->update($this->currentBarangay->require(), $request->attributes(), $this->actor($request)->id);
        Toast::success('messages.profile_updated');

        return back();
    }

    public function updateSignatory(SignatoryRequest $request, SignatoryOffice $office): RedirectResponse
    {
        $this->profiles->saveSignatory(
            $this->currentBarangay->require(),
            $office,
            $request->string('name')->trim()->toString(),
            $request->filled('title') ? $request->string('title')->trim()->toString() : null,
            $request->file('signature'),
            $request->boolean('remove_signature'),
            $this->actor($request)->id,
        );
        Toast::success('messages.signatory_updated');

        return back();
    }
}
