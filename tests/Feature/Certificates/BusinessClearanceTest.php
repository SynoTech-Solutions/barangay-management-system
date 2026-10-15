<?php

namespace Tests\Feature\Certificates;

use App\Enums\AuditAction;
use App\Enums\ClearanceValidityMode;
use App\Enums\FeatureFlag;
use App\Enums\IssuedDocumentStatus;
use App\Enums\SignatoryOffice;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\BarangayProfile;
use App\Models\Business;
use App\Models\BusinessClearance;
use App\Models\Resident;
use App\Models\Signatory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class BusinessClearanceTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    public function test_issuing_allocates_both_numbers_snapshots_the_business_and_stores_the_expiry(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-05-20 08:00:00'));
        [$secretary, $barangay, $purok] = $this->residentDesk();
        BarangayProfile::query()->forceCreate(['barangay_id' => $barangay->id, 'clearance_validity_mode' => ClearanceValidityMode::Months, 'clearance_validity_months' => 12]);
        Signatory::query()->create(['office' => SignatoryOffice::PunongBarangay, 'name' => 'Pedro Reyes', 'title' => 'Punong Barangay']);
        $owner = Resident::factory()->named('Dela Cruz', 'Juan')->create();
        $business = Business::factory()->create(['name' => 'Juan Bakery', 'owner_resident_id' => $owner->id, 'owner_name' => null, 'street_address' => 'Main Street', 'purok_id' => $purok->id]);

        $this->actingAs($secretary)
            ->post(route('business-clearances.store'), ['business_id' => $business->id, 'amount' => '500', 'doc_stamp' => '30', 'or_number' => 'OR-77'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('business-clearances.index'));

        $this->inBarangay($barangay);
        $clearance = BusinessClearance::query()->sole();
        $this->assertSame('BUC-2026-000001', $clearance->clearance_number);
        $this->assertSame('BUCC-2026-000001', $clearance->control_number);
        $this->assertSame('2027-05-20', $clearance->expires_on->toDateString());
        $this->assertSame('Juan Bakery', $clearance->business_name);
        $this->assertSame('Main Street, '.$purok->name, $clearance->business_address);
        $this->assertSame('Dela Cruz, Juan', $clearance->owner_name);
        $this->assertSame($owner->id, $clearance->owner_resident_id);
        $this->assertSame('Pedro Reyes', $clearance->certified_by_name);
        $this->assertSame(IssuedDocumentStatus::Issued, $clearance->status);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::BusinessClearanceIssued)->exists());

        $business->update(['name' => 'Renamed Bakery']);
        $this->actingAs($secretary)->get(route('business-clearances.print', $clearance))->assertInertia(fn (Assert $page) => $page
            ->component('business-clearances/Print')
            ->where('business.name', 'Juan Bakery')
            ->where('document.expires_on', '2027-05-20')
            ->where('document.total', '530.00')
            ->where('document.print_count', 1));
    }

    public function test_inactive_businesses_cannot_get_clearances(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $business = Business::factory()->inactive()->create();

        $this->actingAs($secretary)->postJson(route('business-clearances.store'), ['business_id' => $business->id])
            ->assertUnprocessable()->assertJsonPath('error.code', 'business_inactive');

        $this->inBarangay($barangay);
        $this->assertSame(0, BusinessClearance::query()->count());
    }

    public function test_release_cancel_edit_and_reprint_follow_the_document_lifecycle(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $pending = BusinessClearance::factory()->create();
        $cancelled = BusinessClearance::factory()->cancelled()->create(['or_number' => 'OR-1']);

        $this->actingAs($secretary)->post(route('business-clearances.release'), ['ids' => [$pending->id, $cancelled->id]])->assertRedirect();
        $this->inBarangay($barangay);
        $this->assertTrue($pending->refresh()->isPending());

        $this->actingAs($secretary)->put(route('business-clearances.update', $pending), ['or_number' => 'OR-1', 'amount' => '200'])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('business-clearances.cancel'), ['ids' => [$pending->id]])->assertSessionHasErrors('reason');
        $this->actingAs($secretary)->post(route('business-clearances.release'), ['ids' => [$pending->id]])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->putJson(route('business-clearances.update', $pending), ['amount' => '1'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'document_not_editable');

        $this->actingAs($secretary)->get(route('business-clearances.print', $pending))->assertOk();
        $this->actingAs($secretary)->get(route('business-clearances.print', $pending))->assertInertia(fn (Assert $page) => $page->where('document.is_reprint', true));
        $this->actingAs($secretary)->get(route('business-clearances.print', $cancelled))->assertUnprocessable();

        $this->inBarangay($barangay);
        $pending->refresh();
        $this->assertSame(IssuedDocumentStatus::Released, $pending->status);
        $this->assertSame('OR-1', $pending->or_number);
        $this->assertSame('200.00', $pending->amount);
        $this->assertSame(2, AuditEvent::query()->where('action', AuditAction::BusinessClearancePrinted)->count());
    }

    public function test_the_expired_list_ignores_cancelled_clearances_and_renew_prefills_the_business(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-02-01 09:00:00'));
        [$viewer, $barangay] = $this->residentDesk();
        $expired = Business::factory()->create(['name' => 'Expired Store']);
        BusinessClearance::factory()->expiredOn('2026-12-31')->create(['business_id' => $expired->id]);
        BusinessClearance::factory()->cancelled()->create(['business_id' => $expired->id, 'expires_on' => '2027-12-31']);
        $valid = Business::factory()->create();
        BusinessClearance::factory()->expiredOn('2027-12-31')->create(['business_id' => $valid->id]);
        $neverCleared = Business::factory()->create();
        $inactive = Business::factory()->inactive()->create();
        BusinessClearance::factory()->expiredOn('2026-12-31')->create(['business_id' => $inactive->id]);

        $this->actingAs($viewer)->get(route('business-clearances.expired'))->assertInertia(fn (Assert $page) => $page
            ->component('business-clearances/Expired')
            ->where('businesses.meta.total', 1)
            ->where('businesses.data.0.id', $expired->id)
            ->where('businesses.data.0.last_expires_on', '2026-12-31'));

        $this->actingAs($viewer)->get(route('business-clearances.create', ['business_id' => $expired->id]))->assertInertia(fn (Assert $page) => $page
            ->component('business-clearances/Form')
            ->where('business.id', $expired->id)
            ->where('business.name', 'Expired Store'));

        $this->inBarangay($barangay);
        $this->assertFalse($neverCleared->clearances()->exists());
    }

    public function test_without_the_release_step_business_clearances_release_on_issue(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->turnOff(FeatureFlag::DocumentReleaseStep, $barangay);
        $business = Business::factory()->create();

        $this->actingAs($secretary)->post(route('business-clearances.store'), ['business_id' => $business->id])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $clearance = BusinessClearance::query()->sole();
        $this->assertSame(IssuedDocumentStatus::Released, $clearance->status);
        $this->assertSame($secretary->id, $clearance->released_by);
    }

    public function test_tenant_isolation_permissions_and_flag(): void
    {
        [$clerk, $barangay] = $this->residentDesk(SystemRole::Clerk);
        $clearance = BusinessClearance::factory()->create();
        [, $other] = $this->residentDesk();
        $this->inBarangay($other);
        $outsider = BusinessClearance::factory()->create();
        $this->inBarangay($barangay);

        $this->actingAs($clerk)->get(route('business-clearances.edit', $outsider))->assertNotFound();
        $this->actingAs($clerk)->get(route('business-clearances.print', $outsider))->assertNotFound();
        $this->actingAs($clerk)->post(route('business-clearances.store'), ['business_id' => $outsider->business_id])->assertSessionHasErrors('business_id');
        $this->actingAs($clerk)->post(route('business-clearances.release'), ['ids' => [$clearance->id]])->assertForbidden();

        $viewer = $this->barangayUser(SystemRole::Viewer, $barangay);
        $this->actingAs($viewer)->get(route('business-clearances.index'))->assertOk();
        $this->actingAs($viewer)->get(route('business-clearances.create'))->assertForbidden();

        $this->turnOff(FeatureFlag::BusinessClearance, $barangay);
        $this->actingAs($viewer)->get(route('business-clearances.index'))->assertNotFound();
        $this->actingAs($viewer)->get(route('business-clearances.expired'))->assertNotFound();
    }
}
