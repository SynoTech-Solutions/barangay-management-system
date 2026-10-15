<?php

namespace App\Http\Middleware;

use App\Enums\ErrorCode;
use App\Enums\Permission;
use App\Models\Barangay;
use App\Models\User;
use App\Services\Access\PermissionResolver;
use App\Support\RouteNames;
use App\Support\SessionKeys;
use App\Tenancy\CurrentBarangay;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolveCurrentBarangay
{
    private const string USER_CONTEXT_KEY = 'user_id';

    private const string BARANGAY_CONTEXT_KEY = 'barangay_id';

    public function __construct(
        private CurrentBarangay $currentBarangay,
        private PermissionResolver $permissions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            if (! $user->is_active) {
                return $this->signOut($request, ErrorCode::AccountInactive);
            }

            $barangay = $user->isGlobalStaff()
                ? $this->selectedBarangay($request, $user)
                : $user->barangay;

            if ($barangay !== null && ! $barangay->isActive() && ! $user->isGlobalStaff()) {
                return $this->signOut($request, ErrorCode::BarangaySuspended);
            }

            $this->currentBarangay->set($barangay);

            Context::add(self::USER_CONTEXT_KEY, $user->id);
            Context::add(self::BARANGAY_CONTEXT_KEY, $barangay?->id);
        }

        return $next($request);
    }

    private function signOut(Request $request, ErrorCode $reason): Response
    {
        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route(RouteNames::LOGIN)->withErrors([Fortify::username() => __($reason->messageKey())]);
    }

    private function selectedBarangay(Request $request, User $user): ?Barangay
    {
        $selected = $request->session()->get(SessionKeys::ACTIVE_BARANGAY_ID);

        if (! is_int($selected) || ! $this->permissions->has($user, Permission::BarangaysSwitch, null)) {
            return null;
        }

        return Barangay::query()->find($selected);
    }
}
