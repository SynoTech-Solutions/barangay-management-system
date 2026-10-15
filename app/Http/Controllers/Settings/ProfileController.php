<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Support\Toast;
use App\Services\Users\SelfService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/Profile');
    }

    public function update(ProfileUpdateRequest $request, SelfService $selfService): RedirectResponse
    {
        $user = $this->actor($request);

        $selfService->updateProfile($user, $request->profile());
        Toast::success('messages.own_profile_updated');

        return back();
    }
}
