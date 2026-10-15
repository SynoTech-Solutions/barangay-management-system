<?php

namespace Tests\Feature\Certificates;

use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Enums\IssuedDocumentStatus;
use App\Enums\SignatoryOffice;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\Cedula;
use App\Models\CedulaTemplate;
use App\Models\Resident;
use App\Models\Signatory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class CedulaTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    public function test_issuing_stores_the_number_inputs_and_computed_breakdown_and_audits(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-20 09:00:00'));
        [$treasurer, $barangay] = $this->residentDesk(SystemRole::Treasurer);
        Signatory::query()->create(['office' => SignatoryOffice::Treasurer, 'name' => 'Rosa Lim', 'title' => 'Barangay Treasurer']);
        $resident = Resident::factory()->create();

        $this->actingAs($treasurer)->post(route('cedulas.store'), [
            'resident_id' => $resident->id,
            'form_number' => ' 0012345 ',
            'business_gross_receipts' => '250000',
            'salaries' => '180500.75',
            'real_property_income' => '999.99',
        ])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $cedula = Cedula::query()->sole();
        $this->assertSame('CTC-2026-000001', $cedula->cedula_number);
        $this->assertSame('0012345', $cedula->form_number);
        $this->assertSame(2026, $cedula->tax_year);
        $this->assertSame('5.00', $cedula->basic_tax);
        $this->assertSame('250.00', $cedula->business_tax);
        $this->assertSame('180.00', $cedula->salary_tax);
        $this->assertSame('0.00', $cedula->real_property_tax);
        $this->assertSame('430.00', $cedula->additional_tax);
        $this->assertSame('0.00', $cedula->interest);
        $this->assertSame('435.00', $cedula->amount);
        $this->assertSame(1, $cedula->breakdown['v']);
        $this->assertSame('435.00', $cedula->breakdown['total']);
        $this->assertSame('180500.75', $cedula->breakdown['inputs']['salaries']);
        $this->assertSame('Rosa Lim', $cedula->certified_by_name);
        $this->assertStringContainsString($barangay->name, $cedula->place_of_issue);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::CedulaIssued)->where('subject_id', (string) $cedula->id)->exists());
    }

    public function test_interest_is_added_only_when_the_option_is_on(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-04-10 09:00:00'));
        [$treasurer, $barangay] = $this->residentDesk(SystemRole::Treasurer);
        $resident = Resident::factory()->create();

        $this->actingAs($treasurer)->getJson(route('cedulas.quote', ['salaries' => '100000']))
            ->assertOk()->assertJsonPath('data.total', '105.00')->assertJsonPath('data.interest_applied', false);

        $this->turnOn(FeatureFlag::CedulaInterest, $barangay);
        $this->actingAs($treasurer)->post(route('cedulas.store'), ['resident_id' => $resident->id, 'salaries' => '100000'])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $cedula = Cedula::query()->sole();
        $this->assertSame(2, $cedula->breakdown['interest_months']);
        $this->assertSame('4.20', $cedula->interest);
        $this->assertSame('109.20', $cedula->amount);
    }

    public function test_stored_breakdowns_do_not_change_when_settings_change(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-10 09:00:00'));
        [$treasurer, $barangay] = $this->residentDesk(SystemRole::Treasurer);
        $this->actingAs($treasurer)->post(route('cedulas.store'), ['resident_id' => Resident::factory()->create()->id])->assertSessionHasNoErrors();

        $this->turnOn(FeatureFlag::CedulaInterest, $barangay);
        $this->inBarangay($barangay);
        $cedula = Cedula::query()->sole();

        $this->actingAs($treasurer)->get(route('cedulas.print', $cedula))->assertInertia(fn (Assert $page) => $page
            ->component('cedulas/Print')
            ->where('document.breakdown.total', '5.00')
            ->where('document.print_count', 1));
        $this->actingAs($treasurer)->get(route('cedulas.show', $cedula))->assertInertia(fn (Assert $page) => $page->component('cedulas/Show')->where('cedula.amount', '5.00'));
    }

    public function test_form_numbers_are_unique_among_active_cedulas_and_deceased_residents_are_refused(): void
    {
        [$treasurer] = $this->residentDesk(SystemRole::Treasurer);
        Cedula::factory()->create(['form_number' => 'F-1']);
        Cedula::factory()->cancelled()->create(['form_number' => 'F-2']);
        $resident = Resident::factory()->create();

        $this->actingAs($treasurer)->post(route('cedulas.store'), ['resident_id' => $resident->id, 'form_number' => 'F-1'])->assertSessionHasErrors('form_number');
        $this->actingAs($treasurer)->post(route('cedulas.store'), ['resident_id' => $resident->id, 'form_number' => 'F-2'])->assertSessionHasNoErrors();
        $this->actingAs($treasurer)->postJson(route('cedulas.store'), ['resident_id' => Resident::factory()->deceased()->create()->id])
            ->assertUnprocessable()->assertJsonPath('error.code', 'resident_deceased');
    }

    public function test_release_and_cancel_reuse_the_document_lifecycle(): void
    {
        [$treasurer, $barangay] = $this->residentDesk(SystemRole::Treasurer);
        $pending = Cedula::factory()->create();
        $released = Cedula::factory()->released()->create();

        $this->actingAs($treasurer)->post(route('cedulas.release'), ['ids' => [$pending->id, $released->id]])->assertRedirect();
        $this->actingAs($treasurer)->post(route('cedulas.cancel'), ['ids' => [$pending->id]])->assertSessionHasErrors('reason');
        $this->actingAs($treasurer)->post(route('cedulas.cancel'), ['ids' => [$pending->id], 'reason' => 'Wrong taxpayer'])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $this->assertSame(IssuedDocumentStatus::Cancelled, $pending->refresh()->status);
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::CedulaCancelled)->count());
        $this->assertFalse(AuditEvent::query()->where('action', AuditAction::CedulaReleased)->exists());
        $this->actingAs($treasurer)->get(route('cedulas.print', $pending))->assertUnprocessable();
    }

    public function test_templates_prefill_the_form_and_hidden_templates_are_left_out(): void
    {
        [$treasurer, $barangay] = $this->residentDesk(SystemRole::Treasurer);

        $this->actingAs($treasurer)->post(route('cedulas.templates.store'), ['name' => 'Market vendor', 'business_gross_receipts' => '150000'])->assertSessionHasNoErrors();
        $this->actingAs($treasurer)->post(route('cedulas.templates.store'), ['name' => 'market VENDOR'])->assertSessionHasErrors('name');
        $hidden = CedulaTemplate::factory()->create(['name' => 'Retired', 'is_active' => false]);

        $this->actingAs($treasurer)->get(route('cedulas.create'))->assertInertia(fn (Assert $page) => $page
            ->component('cedulas/Form')
            ->has('templates', 1)
            ->where('templates.0.name', 'Market vendor')
            ->where('templates.0.business_gross_receipts', '150000.00'));

        $this->actingAs($treasurer)->post(route('cedulas.templates.reactivate', $hidden))->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $this->assertTrue($hidden->refresh()->is_active);
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::CedulaTemplateSaved)->count());
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::Reactivated)->where('subject_id', (string) $hidden->id)->count());

        $this->turnOff(FeatureFlag::CedulaTemplates, $barangay);
        $this->actingAs($treasurer)->get(route('cedulas.templates.index'))->assertNotFound();
    }

    public function test_the_cedula_reference_endpoint_is_scoped_to_the_resident_and_barangay(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $resident = Resident::factory()->create();
        $own = Cedula::factory()->create(['resident_id' => $resident->id, 'form_number' => 'F-77']);
        Cedula::factory()->cancelled()->create(['resident_id' => $resident->id]);
        Cedula::factory()->create();
        [, $other] = $this->residentDesk();
        $this->inBarangay($other);
        $outsider = Resident::factory()->create();
        Cedula::factory()->create(['resident_id' => $outsider->id]);
        $this->inBarangay($barangay);

        $this->actingAs($secretary)->getJson(route('cedulas.references', ['resident_id' => $resident->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.ctc_number', 'F-77')
            ->assertJsonPath('data.0.ctc_place', $own->place_of_issue)
            ->assertJsonPath('data.0.ctc_date', $own->issued_at->toDateString());
        $this->actingAs($secretary)->getJson(route('cedulas.references', ['resident_id' => $outsider->id]))->assertJsonValidationErrors('resident_id');
    }

    public function test_permissions_flags_and_tenant_isolation(): void
    {
        [$clerk, $barangay] = $this->residentDesk(SystemRole::Clerk);
        $cedula = Cedula::factory()->create();
        [, $other] = $this->residentDesk();
        $this->inBarangay($other);
        $outsider = Cedula::factory()->create();
        $this->inBarangay($barangay);
        $viewer = $this->barangayUser(SystemRole::Viewer, $barangay);

        $this->actingAs($viewer)->get(route('cedulas.index'))->assertOk();
        $this->actingAs($viewer)->get(route('cedulas.create'))->assertForbidden();
        $this->actingAs($clerk)->get(route('cedulas.create'))->assertOk();
        $this->actingAs($clerk)->post(route('cedulas.release'), ['ids' => [$cedula->id]])->assertForbidden();
        $this->actingAs($clerk)->get(route('cedulas.show', $outsider))->assertNotFound();
        $this->actingAs($clerk)->get(route('cedulas.print', $outsider))->assertNotFound();

        $this->turnOff(FeatureFlag::Cedula, $barangay);
        $this->actingAs($viewer)->get(route('cedulas.index'))->assertNotFound();
        $this->actingAs($clerk)->post(route('cedulas.store'), ['resident_id' => $cedula->resident_id])->assertRedirect();
        $this->actingAs($clerk)->getJson(route('cedulas.references', ['resident_id' => $cedula->resident_id]))->assertNotFound();
    }
}
