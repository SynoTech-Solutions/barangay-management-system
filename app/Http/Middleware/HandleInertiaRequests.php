<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Access\PermissionResolver;
use App\Services\Features\FeatureFlags;
use App\Tenancy\CurrentBarangay;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    private const string SIDEBAR_COOKIE = 'sidebar_state';

    protected $rootView = 'app';

    public function __construct(
        private readonly CurrentBarangay $currentBarangay,
        private readonly FeatureFlags $features,
        private readonly PermissionResolver $permissions,
    ) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $barangay = $this->currentBarangay->get();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user instanceof User ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'designation' => $user->designation,
                    'is_global_staff' => $user->isGlobalStaff(),
                    'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                ] : null,
                'permissions' => $user instanceof User ? $this->permissions->list($user) : [],
            ],
            'barangay' => $barangay === null ? null : [
                'id' => $barangay->id,
                'name' => $barangay->name,
                'psgc_code' => $barangay->psgc_code,
            ],
            'features' => $user instanceof User ? $this->features->enabledMap() : [],
            'sidebarOpen' => ! $request->hasCookie(self::SIDEBAR_COOKIE) || $request->cookie(self::SIDEBAR_COOKIE) === 'true',
        ];
    }
}
