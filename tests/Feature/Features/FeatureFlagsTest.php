<?php

namespace Tests\Feature\Features;

use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Enums\FlagSource;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\Barangay;
use App\Services\Features\FeatureFlags;
use App\Support\Config\BmsSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_overrides_apply_per_barangay(): void
    {
        [$first, $second] = Barangay::factory()->count(2)->create()->all();
        $features = app(FeatureFlags::class);

        $features->override(FeatureFlag::Sms, $first, true, 'pilot', null);

        $this->assertTrue($features->enabled(FeatureFlag::Sms, $first));
        $this->assertFalse($features->enabled(FeatureFlag::Sms, $second));
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::FeatureOverridden)->where('barangay_id', $first->id)->exists());
    }

    public function test_global_overrides_apply_to_every_barangay_without_their_own(): void
    {
        $barangay = Barangay::factory()->create();
        $features = app(FeatureFlags::class);

        $features->override(FeatureFlag::Sms, null, true, null, null);

        $this->assertSame(FlagSource::GlobalOverride, $features->state(FeatureFlag::Sms, $barangay)->source);
    }

    public function test_clearing_an_override_restores_the_default(): void
    {
        $barangay = Barangay::factory()->create();
        $features = app(FeatureFlags::class);

        $features->override(FeatureFlag::BarangayClearance, $barangay, false, null, null);
        $features->clearOverride(FeatureFlag::BarangayClearance, $barangay, null);

        $state = $features->state(FeatureFlag::BarangayClearance, $barangay);
        $this->assertTrue($state->enabled);
        $this->assertSame(FlagSource::Default, $state->source);
    }

    public function test_operator_kill_switch_from_config_wins(): void
    {
        config(['bms.features.forced_off' => [FeatureFlag::Sms->value]]);
        $this->app->forgetInstance(BmsSettings::class);
        $this->app->forgetInstance(FeatureFlags::class);
        $barangay = Barangay::factory()->create();

        $features = app(FeatureFlags::class);
        $features->override(FeatureFlag::Sms, $barangay, true, null, null);

        $this->assertSame(FlagSource::ForcedOff, $features->state(FeatureFlag::Sms, $barangay)->source);
    }

    public function test_barangay_admin_can_toggle_a_flag_for_their_barangay(): void
    {
        $admin = $this->barangayUser(SystemRole::BarangayAdmin);

        $this->actingAs($admin)
            ->put(route('admin.features.update'), ['flag' => 'sms', 'state' => 'on', 'scope' => 'barangay', 'reason' => 'pilot'])
            ->assertRedirect();

        $this->assertTrue(app(FeatureFlags::class)->enabled(FeatureFlag::Sms, $admin->barangay));
    }

    public function test_barangay_admin_cannot_change_global_overrides(): void
    {
        $admin = $this->barangayUser(SystemRole::BarangayAdmin);

        $this->actingAs($admin)
            ->put(route('admin.features.update'), ['flag' => 'sms', 'state' => 'on', 'scope' => 'global'])
            ->assertForbidden();
    }

    public function test_clerks_cannot_view_feature_settings(): void
    {
        $this->actingAs($this->barangayUser(SystemRole::Clerk))
            ->get(route('admin.features.index'))
            ->assertForbidden();
    }

    public function test_feature_middleware_hides_disabled_routes(): void
    {
        $user = $this->barangayUser(SystemRole::Clerk);
        app(FeatureFlags::class)->override(FeatureFlag::UserTheme, $user->barangay, false, null, null);

        $this->actingAs($user)->get(route('appearance.edit'))->assertNotFound();
    }

    public function test_cli_can_set_and_list_flags(): void
    {
        $barangay = Barangay::factory()->create();

        $this->artisan('bms:features:set', ['flag' => 'sms', 'state' => 'on', '--barangay' => $barangay->psgc_code])
            ->assertSuccessful();
        $this->artisan('bms:features:list', ['--barangay' => $barangay->psgc_code])->assertSuccessful();
        $this->artisan('bms:features:set', ['flag' => 'nope', 'state' => 'on'])->assertFailed();

        $this->assertTrue(app(FeatureFlags::class)->enabled(FeatureFlag::Sms, $barangay));
    }
}
