<?php

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\Barangay;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_users_sign_in_with_their_username_in_any_case(): void
    {
        $user = $this->barangayUser(SystemRole::Clerk);

        $response = $this->post(route('login.store'), [
            'username' => strtoupper($user->username),
            'password' => UserFactory::PASSWORD,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertNotNull($user->refresh()->last_login_at);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::LoggedIn)->where('actor_id', $user->id)->exists());
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'username' => $user->username,
            'password' => UserFactory::PASSWORD,
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected_and_audited(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::LoginFailed)->exists());
    }

    public function test_inactive_accounts_cannot_sign_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => UserFactory::PASSWORD,
        ])->assertSessionHasErrors(['username' => __('errors.account_inactive')]);

        $this->assertGuest();
    }

    public function test_accounts_of_suspended_barangays_cannot_sign_in(): void
    {
        $user = $this->barangayUser(SystemRole::Clerk, Barangay::factory()->suspended()->create());

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => UserFactory::PASSWORD,
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_a_session_is_ended_when_the_account_is_deactivated(): void
    {
        $user = $this->barangayUser(SystemRole::Clerk);
        $this->actingAs($user);

        $user->forceFill(['is_active' => false])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect(route('home'));
    }

    public function test_users_are_rate_limited(): void
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->username, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
