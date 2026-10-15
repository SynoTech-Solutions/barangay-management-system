<?php

namespace Tests\Feature\Settings;

use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Services\Features\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs($this->barangayUser(SystemRole::Clerk))
            ->get(route('profile.edit'))
            ->assertOk();
    }

    public function test_profile_information_can_be_updated_and_is_audited(): void
    {
        $user = $this->barangayUser(SystemRole::Clerk);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'New Name', 'email' => 'NEW@example.com'])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@example.com', $user->email);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::Updated)->where('subject_id', (string) $user->id)->exists());
    }

    public function test_profile_updates_are_blocked_when_self_service_is_off(): void
    {
        $user = $this->barangayUser(SystemRole::Clerk);
        app(FeatureFlags::class)->override(FeatureFlag::SelfServiceProfile, $user->barangay, false, null, null);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'Blocked'])
            ->assertRedirect();

        $this->assertNotSame('Blocked', $user->refresh()->name);
    }

    public function test_accounts_cannot_be_self_deleted(): void
    {
        $this->actingAs($this->barangayUser(SystemRole::Clerk))
            ->delete('/settings/profile')
            ->assertStatus(405);
    }
}
