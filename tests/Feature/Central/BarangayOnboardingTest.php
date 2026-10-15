<?php

namespace Tests\Feature\Central;

use App\Enums\BarangayStatus;
use App\Enums\SystemRole;
use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\User;
use App\Support\SessionKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BarangayOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_admin_onboards_a_barangay_with_its_first_admin(): void
    {
        $municipality = Municipality::factory()->create();

        $this->actingAs($this->centralAdmin())->post(route('central.barangays.store'), [
            'municipality_id' => $municipality->id,
            'psgc_code' => '9902801001',
            'name' => 'Barangay Dos',
            'admin' => [
                'name' => 'First Admin',
                'username' => 'First.Admin',
                'password' => 'long-enough-pass-1',
                'password_confirmation' => 'long-enough-pass-1',
            ],
        ])->assertRedirect(route('central.barangays.index'));

        $barangay = Barangay::query()->where('psgc_code', '9902801001')->firstOrFail();
        $admin = User::query()->where('username', 'first.admin')->firstOrFail();

        $this->assertSame($barangay->id, $admin->barangay_id);
        $this->inBarangay($barangay);
        $this->assertNotNull($barangay->profile);
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    }

    public function test_barangay_admins_cannot_reach_central_screens(): void
    {
        $admin = $this->barangayUser(SystemRole::BarangayAdmin);

        $this->actingAs($admin)->get(route('central.barangays.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('central.switch.store', $admin->barangay))->assertForbidden();
    }

    public function test_central_admin_switches_into_a_barangay(): void
    {
        $central = $this->centralAdmin();
        $barangay = Barangay::factory()->create();

        $this->actingAs($central)
            ->post(route('central.switch.store', $barangay))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(SessionKeys::ACTIVE_BARANGAY_ID, $barangay->id);

        $this->actingAs($central)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('barangay.id', $barangay->id));

        $this->actingAs($central)->get(route('admin.users.index'))->assertOk();
    }

    public function test_suspending_a_barangay_blocks_its_users(): void
    {
        $central = $this->centralAdmin();
        $clerk = $this->barangayUser(SystemRole::Clerk);

        $this->actingAs($central)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('central.barangays.status.update', $clerk->barangay), ['status' => BarangayStatus::Suspended->value])
            ->assertRedirect();

        $this->actingAs($clerk->refresh())->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_municipality_search_is_bounded(): void
    {
        Municipality::factory()->count(25)->create();

        $this->actingAs($this->centralAdmin())
            ->getJson(route('central.municipalities.search'))
            ->assertOk()
            ->assertJsonCount(20, 'data');
    }
}
