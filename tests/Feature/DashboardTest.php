<?php

namespace Tests\Feature;

use App\Enums\SystemRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_barangay_users_see_their_barangay_features_and_permissions(): void
    {
        $user = $this->barangayUser(SystemRole::Clerk);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('barangay.id', $user->barangay_id)
                ->where('features.sms', false)
                ->where('features.barangay-clearance', true)
                ->where('auth.user.username', $user->username)
                ->has('auth.permissions'));
    }

    public function test_responses_carry_a_request_id(): void
    {
        $this->get(route('login'))->assertHeader('X-Request-Id');
    }

    public function test_safe_inbound_request_ids_are_kept(): void
    {
        $this->get(route('login'), ['X-Request-Id' => 'trace-123'])->assertHeader('X-Request-Id', 'trace-123');
    }
}
