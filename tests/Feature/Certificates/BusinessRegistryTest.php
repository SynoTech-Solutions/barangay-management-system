<?php

namespace Tests\Feature\Certificates;

use App\Enums\AuditAction;
use App\Enums\BusinessStatus;
use App\Enums\FeatureFlag;
use App\Enums\LookupType;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\Business;
use App\Models\Purok;
use App\Models\Resident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class BusinessRegistryTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => '  Juan   Sari-sari Store ',
            'owner_name' => 'Juan Dela Cruz',
            'category_id' => $this->lookup(LookupType::BusinessCategory)->id,
            'nature_id' => $this->lookup(LookupType::BusinessNature)->id,
            'ownership_type_id' => $this->lookup(LookupType::BusinessOwnershipType)->id,
            'street_address' => 'Main Street',
            'capitalization' => '15000.50',
            'gross_receipts' => '120000',
            ...$overrides,
        ];
    }

    public function test_registering_allocates_a_business_number_normalizes_the_name_and_audits(): void
    {
        [$secretary, $barangay, $purok] = $this->residentDesk();

        $this->actingAs($secretary)->post(route('businesses.store'), $this->payload(['purok_id' => $purok->id]))->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $business = Business::query()->sole();
        $this->assertSame('BUS-000001', $business->business_number);
        $this->assertSame('Juan Sari-sari Store', $business->name);
        $this->assertSame('15000.50', $business->capitalization);
        $this->assertSame('120000.00', $business->gross_receipts);
        $this->assertSame(BusinessStatus::Active, $business->status);
        $this->assertSame($secretary->id, $business->registered_by);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::BusinessRegistered)->where('subject_id', (string) $business->id)->exists());
    }

    public function test_the_owner_is_a_resident_or_free_text_but_not_both_or_neither(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $owner = Resident::factory()->create();

        $this->actingAs($secretary)->post(route('businesses.store'), $this->payload(['owner_name' => null]))->assertSessionHasErrors(['owner_name', 'owner_resident_id']);
        $this->actingAs($secretary)->post(route('businesses.store'), $this->payload(['owner_resident_id' => $owner->id]))->assertSessionHasErrors('owner_name');
        $this->actingAs($secretary)->post(route('businesses.store'), $this->payload(['owner_name' => null, 'owner_resident_id' => $owner->id]))->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $this->assertSame($owner->id, Business::query()->sole()->owner_resident_id);
    }

    public function test_business_names_are_unique_per_barangay_ignoring_case(): void
    {
        [$secretary] = $this->residentDesk();
        Business::factory()->create(['name' => 'Juan Sari-sari Store']);
        [, $other] = $this->residentDesk();
        $this->inBarangay($other);
        Business::factory()->create(['name' => 'Other Store']);

        $this->actingAs($secretary)->post(route('businesses.store'), $this->payload(['name' => 'juan sari-sari store']))->assertSessionHasErrors('name');
        $this->actingAs($secretary)->post(route('businesses.store'), $this->payload(['name' => 'Other Store']))->assertSessionHasNoErrors();
    }

    public function test_updates_and_status_changes_are_audited(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $business = Business::factory()->create();

        $this->actingAs($secretary)->put(route('businesses.update', $business), $this->payload([
            'name' => $business->name,
            'category_id' => $business->category_id,
            'nature_id' => $business->nature_id,
            'ownership_type_id' => $business->ownership_type_id,
            'plate_number' => 'PL-1',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('businesses.show', $business));
        $this->actingAs($secretary)->post(route('businesses.status', $business), ['status' => 'inactive'])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $business->refresh();
        $this->assertSame('PL-1', $business->plate_number);
        $this->assertSame(BusinessStatus::Inactive, $business->status);
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::BusinessUpdated)->count());
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::BusinessStatusChanged)->count());
    }

    public function test_the_list_filters_and_the_picker_returns_only_active_businesses(): void
    {
        [$viewer] = $this->residentDesk(SystemRole::Viewer);
        $active = Business::factory()->create(['name' => 'Bakery Uno']);
        Business::factory()->inactive()->create(['name' => 'Bakery Dos']);

        $this->actingAs($viewer)->get(route('businesses.index', ['search' => 'bakery']))
            ->assertInertia(fn (Assert $page) => $page->component('businesses/Index')->where('businesses.meta.total', 2));
        $this->actingAs($viewer)->get(route('businesses.index', ['status' => 'inactive']))
            ->assertInertia(fn (Assert $page) => $page->where('businesses.meta.total', 1)->where('businesses.data.0.name', 'Bakery Dos'));
        $this->actingAs($viewer)->get(route('businesses.index', ['category_id' => $active->category_id]))
            ->assertInertia(fn (Assert $page) => $page->where('businesses.meta.total', 1));

        $this->actingAs($viewer)->getJson(route('businesses.search', ['q' => 'bakery']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
        $this->actingAs($viewer)->get(route('businesses.show', $active))->assertInertia(fn (Assert $page) => $page->component('businesses/Show'));
    }

    public function test_dimension_report_counts_respect_the_status_filter(): void
    {
        [$viewer, , $purok] = $this->residentDesk(SystemRole::Viewer);
        $category = $this->lookup(LookupType::BusinessCategory, 'Retail');
        Business::factory()->count(2)->create(['category_id' => $category->id, 'purok_id' => $purok->id]);
        Business::factory()->inactive()->create(['category_id' => $category->id]);

        $this->actingAs($viewer)->get(route('reports.businesses', ['dimension' => 'category']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/Businesses')
                ->where('rows.0.key', (string) $category->id)
                ->where('rows.0.label', $category->label)
                ->where('rows.0.total', 3));
        $this->actingAs($viewer)->get(route('reports.businesses', ['dimension' => 'category', 'status' => 'active']))
            ->assertInertia(fn (Assert $page) => $page->where('rows.0.total', 2)->has('rows', 1));
        $this->actingAs($viewer)->get(route('reports.businesses', ['dimension' => 'purok']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 2)
                ->where('rows.0.key', (string) $purok->id)
                ->where('rows.0.total', 2)
                ->where('rows.1.key', 'none')
                ->where('rows.1.total', 1));
        $this->actingAs($viewer)->get(route('reports.businesses', ['dimension' => 'status']))
            ->assertInertia(fn (Assert $page) => $page->where('rows.0.key', 'active')->where('rows.0.total', 2)->where('rows.1.label', 'Inactive'));
    }

    public function test_permissions_flags_and_tenant_isolation_are_enforced(): void
    {
        [$clerk, $barangay] = $this->residentDesk(SystemRole::Clerk);
        $business = Business::factory()->create();
        [, $other] = $this->residentDesk();
        $this->inBarangay($other);
        $outsider = Business::factory()->create(['owner_resident_id' => Resident::factory()->create()->id, 'owner_name' => null]);
        Purok::factory()->create();
        $this->inBarangay($barangay);

        $this->actingAs($clerk)->get(route('businesses.index'))->assertOk();
        $this->actingAs($clerk)->get(route('businesses.create'))->assertForbidden();
        $this->actingAs($clerk)->post(route('businesses.status', $business), ['status' => 'inactive'])->assertForbidden();
        $this->actingAs($clerk)->get(route('businesses.show', $outsider))->assertNotFound();

        $admin = $this->barangayUser(SystemRole::BarangayAdmin, $barangay);
        $this->actingAs($admin)->get(route('businesses.edit', $outsider))->assertNotFound();

        $this->turnOff(FeatureFlag::BusinessRegistry, $barangay);
        $this->actingAs($clerk)->get(route('businesses.index'))->assertNotFound();
        $this->actingAs($clerk)->get(route('reports.businesses'))->assertNotFound();
        $this->actingAs($clerk)->get(route('business-clearances.index'))->assertNotFound();
    }
}
