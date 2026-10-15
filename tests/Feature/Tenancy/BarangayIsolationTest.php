<?php

namespace Tests\Feature\Tenancy;

use App\Enums\SignatoryOffice;
use App\Enums\SystemRole;
use App\Exceptions\BmsException;
use App\Models\Barangay;
use App\Models\Signatory;
use App\Tenancy\CurrentBarangay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarangayIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_models_only_return_rows_of_the_current_barangay(): void
    {
        [$first, $second] = Barangay::factory()->count(2)->create()->all();

        $this->inBarangay($first);
        Signatory::query()->create(['office' => SignatoryOffice::Secretary, 'name' => 'First']);
        $this->inBarangay($second);
        Signatory::query()->create(['office' => SignatoryOffice::Secretary, 'name' => 'Second']);

        $this->assertSame(['Second'], Signatory::query()->pluck('name')->all());

        $this->inBarangay($first);
        $this->assertSame(['First'], Signatory::query()->pluck('name')->all());
        $this->assertCount(2, Signatory::acrossBarangays()->get());
    }

    public function test_tenant_queries_fail_closed_without_a_barangay(): void
    {
        app(CurrentBarangay::class)->set(null);

        $this->expectException(BmsException::class);

        Signatory::query()->get();
    }

    public function test_admins_cannot_open_accounts_of_another_barangay(): void
    {
        $admin = $this->barangayUser(SystemRole::BarangayAdmin);
        $outsider = $this->barangayUser(SystemRole::Clerk);

        $this->actingAs($admin)->get(route('admin.users.edit', $outsider))->assertNotFound();
        $this->actingAs($admin)->post(route('admin.users.deactivate', $outsider))->assertNotFound();
    }

    public function test_barangay_screens_require_a_selected_barangay_for_central_staff(): void
    {
        $this->actingAs($this->centralAdmin())
            ->get(route('admin.users.index'))
            ->assertStatus(409);
    }
}
