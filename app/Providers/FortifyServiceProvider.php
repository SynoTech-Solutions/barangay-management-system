<?php

namespace App\Providers;

use App\Enums\LogEvent;
use App\Models\User;
use App\Support\Config\BmsSettings;
use App\Support\RateLimiters;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    private const string TWO_FACTOR_SESSION_KEY = 'login.id';

    public function boot(): void
    {
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $username = Str::lower(trim((string) $request->input(Fortify::username())));

            $user = User::query()
                ->with('barangay')
                ->whereRaw('lower(username) = ?', [$username])
                ->first();

            if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            if (! $user->is_active || ($user->barangay !== null && ! $user->barangay->isActive())) {
                Log::warning(LogEvent::LoginRejectedInactive->value, ['user_id' => $user->id]);

                throw ValidationException::withMessages([
                    Fortify::username() => __('errors.account_inactive'),
                ]);
            }

            return $user;
        });
    }

    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    private function configureRateLimiting(): void
    {
        $settings = $this->app->make(BmsSettings::class);

        RateLimiter::for(RateLimiters::TWO_FACTOR, fn (Request $request) => Limit::perMinute($settings->auth->twoFactorMaxAttemptsPerMinute)
            ->by($request->session()->get(self::TWO_FACTOR_SESSION_KEY)));

        RateLimiter::for(RateLimiters::LOGIN, function (Request $request) use ($settings) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute($settings->auth->loginMaxAttemptsPerMinute)->by($throttleKey);
        });

        RateLimiter::for(RateLimiters::PASSWORD_UPDATE, fn (Request $request) => Limit::perMinute($settings->auth->loginMaxAttemptsPerMinute)
            ->by((string) $request->user()?->getAuthIdentifier()));
    }
}
