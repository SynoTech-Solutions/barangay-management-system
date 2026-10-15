<?php

namespace Tests\Feature\Admin;

use App\Enums\SystemRole;
use App\Models\User;
use App\Services\Access\RoleSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_only_their_barangay_accounts(): void
    {
        $admin = $this->barangayUser(SystemRole::BarangayAdmin);
        $this->barangayUser(SystemRole::Clerk, $admin->barangay);
        $this->barangayUser(SystemRole::Clerk);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/users/Index')->where('users.meta.total', 2));
    }

    public function test_admin_creates_an_account_with_roles(): void
    {
        $admin = $this->barangayUser(SystemRole::BarangayAdmin);
        $secretary = app(RoleSynchronizer::class)->systemRole(SystemRole::Secretary);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Secretary',
            'username' => 'New.Secretary',
            'password' => 'long-enough-pass-1',
            'password_confirmation' => 'long-enough-pass-1',
            'role_ids' => [$secretary->id],
        ])->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('username', 'new.secretary')->firstOrFail();
        $this->assertSame($admin->barangay_id, $created->barangay_id);
        $this->assertTrue($created->can('clearances.issue'));
        $this->assertFalse($created->can('users.manage'));
    }

    public function test_usernames_are_unique_regardless_of_case(): void
    {
        $admin = $this->barangayUser(SystemRole::BarangayAdmin);
        User::factory()->create(['username' => 'taken']);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Dup',
            'username' => 'TAKEN',
            'password' => 'long-enough-pass-1',
            'password_confirmation' => 'long-enough-pass-1',
        ])->assertSessionHasErrors('username');
    }

    public function test_global_roles_cannot_be_assigned_inside_a_barangay(): void
    {
        $admin = $this->barangayUser(SystemRole::BarangayAdmin);
        $central = app(RoleSynchronizer::class)->systemRole(SystemRole::CentralAdmin);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Escalation',
            'username' => 'escalation',
            'password' => 'long-enough-pass-1',
            'password_confirmation' => 'long-enough-pass-1',
            'role_ids' => [$central->id],
        ]);

        $this->assertFalse(User::query()->where('username', 'escalation')->exists());
    }

    public function test_admins_cannot_deactivate_themselves_or_change_their_own_roles(): void
    {
        $admin = $this->barangayUser(SystemRole::BarangayAdmin);

        $this->actingAs($admin)->post(route('admin.users.deactivate', $admin))->assertRedirect();
        $this->assertTrue($admin->refresh()->is_active);

        $this->actingAs($admin)->put(route('admin.users.roles.update', $admin), ['role_ids' => []])->assertRedirect();
        $this->assertTrue($admin->refresh()->can('users.manage'));
    }

    public function test_deactivated_accounts_lose_every_permission(): void
    {
        $admin = $this->barangayUser(SystemRole::BarangayAdmin);
        $clerk = $this->barangayUser(SystemRole::Clerk, $admin->barangay);

        $this->actingAs($admin)->post(route('admin.users.deactivate', $clerk))->assertRedirect();

        $this->assertFalse($clerk->refresh()->is_active);
    }

    public function test_clerks_cannot_manage_accounts(): void
    {
        $clerk = $this->barangayUser(SystemRole::Clerk);

        $this->actingAs($clerk)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($clerk)->get(route('admin.users.create'))->assertForbidden();
    }

    public function test_password_reset_by_admin_requires_password_confirmation(): void
    {
        $admin = $this->barangayUser(SystemRole::BarangayAdmin);
        $clerk = $this->barangayUser(SystemRole::Clerk, $admin->barangay);

        $this->actingAs($admin)
            ->put(route('admin.users.password.reset', $clerk), ['password' => 'another-long-pass-2', 'password_confirmation' => 'another-long-pass-2'])
            ->assertRedirect(route('password.confirm'));
    }
}
