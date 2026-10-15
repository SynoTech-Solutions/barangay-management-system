<?php

namespace Tests\Feature\Admin;

use App\Enums\LookupType;
use App\Enums\SystemRole;
use App\Models\LookupValue;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LookupManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LookupSeeder::class);
    }

    public function test_barangay_sees_global_values_and_adds_its_own(): void
    {
        $admin = $this->barangayUser(SystemRole::BarangayAdmin);

        $this->actingAs($admin)
            ->post(route('admin.lookups.store', LookupType::Religion->value), ['label' => '  Local   Church '])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('admin.lookups.index', LookupType::Religion->value))
            ->assertInertia(fn (Assert $page) => $page->where('values', fn ($values) => collect($values)->contains('label', 'Local Church')
                && collect($values)->contains('label', 'Roman Catholic')));
    }

    public function test_duplicate_labels_are_rejected_case_insensitively(): void
    {
        $admin = $this->barangayUser(SystemRole::BarangayAdmin);

        $this->actingAs($admin)->post(route('admin.lookups.store', LookupType::Religion->value), ['label' => 'roman catholic']);

        $this->assertSame(1, LookupValue::query()->whereRaw('lower(label) = ?', ['roman catholic'])->count());
    }

    public function test_barangays_cannot_edit_shared_values(): void
    {
        $admin = $this->barangayUser(SystemRole::BarangayAdmin);
        $shared = LookupValue::query()->whereNull('barangay_id')->where('type', LookupType::Religion)->firstOrFail();

        $this->actingAs($admin)->put(route('admin.lookups.update', [LookupType::Religion->value, $shared->id]), ['label' => 'Changed']);

        $this->assertNotSame('Changed', $shared->refresh()->label);
    }

    public function test_values_are_deactivated_instead_of_deleted(): void
    {
        $admin = $this->barangayUser(SystemRole::BarangayAdmin);
        $this->actingAs($admin)->post(route('admin.lookups.store', LookupType::Skill->value), ['label' => 'Carpentry']);
        $value = LookupValue::query()->where('label', 'Carpentry')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.lookups.deactivate', [LookupType::Skill->value, $value->id]))->assertRedirect();

        $this->assertFalse($value->refresh()->is_active);
    }
}
