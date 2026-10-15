<?php

namespace Tests\Feature\Certificates;

use App\Enums\AuditAction;
use App\Enums\ClearanceValidityMode;
use App\Enums\FeatureFlag;
use App\Enums\IssuedDocumentStatus;
use App\Enums\LookupType;
use App\Enums\SignatoryOffice;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\BarangayClearance;
use App\Models\BarangayProfile;
use App\Models\Blotter;
use App\Models\IdCard;
use App\Models\Resident;
use App\Models\Signatory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class BarangayClearanceTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Resident $resident, array $overrides = []): array
    {
        return [
            'resident_id' => $resident->id,
            'clearance_type_id' => $this->lookup(LookupType::ClearanceType)->id,
            'purpose_id' => $this->lookup(LookupType::ClearancePurpose)->id,
            'amount' => '100.00',
            'doc_stamp' => '30.00',
            ...$overrides,
        ];
    }

    private function holdResident(Resident $held): void
    {
        Blotter::factory()->against($held)->create();
    }

    private function signatories(): void
    {
        Signatory::query()->create(['office' => SignatoryOffice::Secretary, 'name' => 'Ana Santos', 'title' => 'Barangay Secretary']);
        Signatory::query()->create(['office' => SignatoryOffice::PunongBarangay, 'name' => 'Pedro Reyes', 'title' => 'Punong Barangay']);
    }

    public function test_issuing_allocates_both_numbers_snapshots_signatories_and_stores_the_year_end_expiry(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-15 10:00:00'));
        [$secretary, $barangay] = $this->residentDesk();
        $this->signatories();
        $resident = $this->photographedResident();

        $this->actingAs($secretary)
            ->post(route('clearances.store'), $this->payload($resident, ['or_number' => ' OR-1 ']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('clearances.index'));

        $this->inBarangay($barangay);
        $clearance = BarangayClearance::query()->sole();
        $this->assertSame('BC-2026-000001', $clearance->clearance_number);
        $this->assertSame('BCC-2026-000001', $clearance->control_number);
        $this->assertSame(IssuedDocumentStatus::Issued, $clearance->status);
        $this->assertSame('2026-12-31', $clearance->expires_on->toDateString());
        $this->assertSame('Ana Santos', $clearance->verified_by_name);
        $this->assertSame('Pedro Reyes', $clearance->certified_by_name);
        $this->assertSame('OR-1', $clearance->or_number);
        $this->assertSame('100.00', $clearance->amount);
        $this->assertSame('30.00', $clearance->doc_stamp);
        $this->assertSame($secretary->id, $clearance->issued_by);
        $this->assertNull($clearance->released_at);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::ClearanceIssued)->where('subject_id', (string) $clearance->id)->exists());

        Signatory::query()->where('office', SignatoryOffice::PunongBarangay)->update(['name' => 'New Captain']);
        $this->assertSame('Pedro Reyes', $clearance->refresh()->certified_by_name);
    }

    public function test_months_validity_is_stored_at_issue_and_printed_from_the_record(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-31 09:00:00'));
        [$secretary, $barangay] = $this->residentDesk();
        BarangayProfile::query()->forceCreate(['barangay_id' => $barangay->id, 'clearance_validity_mode' => ClearanceValidityMode::Months, 'clearance_validity_months' => 6]);

        $this->actingAs($secretary)->post(route('clearances.store'), $this->payload($this->photographedResident()))->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $clearance = BarangayClearance::query()->sole();
        $this->assertSame('2027-02-28', $clearance->expires_on->toDateString());

        BarangayProfile::query()->whereKey($barangay->id)->update(['clearance_validity_mode' => ClearanceValidityMode::EndOfYear->value, 'clearance_validity_months' => null]);

        $this->actingAs($secretary)->get(route('clearances.print', $clearance))
            ->assertInertia(fn (Assert $page) => $page->component('clearances/Print')->where('document.expires_on', '2027-02-28'));
    }

    public function test_the_secretary_block_flag_controls_the_verified_by_snapshot(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->signatories();
        $this->turnOff(FeatureFlag::SecretaryApprovalBlock, $barangay);

        $this->actingAs($secretary)->post(route('clearances.store'), $this->payload($this->photographedResident()))->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $clearance = BarangayClearance::query()->sole();
        $this->assertNull($clearance->verified_by_name);
        $this->assertSame('Pedro Reyes', $clearance->certified_by_name);
    }

    public function test_issuing_is_blocked_for_missing_photo_deceased_and_removed_residents_without_consuming_numbers(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $noPhoto = Resident::factory()->create();
        $deceased = $this->photographedResident(['is_deceased' => true]);
        $removed = $this->photographedResident();
        $removed->delete();

        $this->actingAs($secretary)->postJson(route('clearances.store'), $this->payload($noPhoto))
            ->assertUnprocessable()->assertJsonPath('error.code', 'resident_photo_required');
        $this->actingAs($secretary)->postJson(route('clearances.store'), $this->payload($deceased))
            ->assertUnprocessable()->assertJsonPath('error.code', 'resident_deceased');
        $this->actingAs($secretary)->postJson(route('clearances.store'), $this->payload($removed))
            ->assertJsonValidationErrors('resident_id');
        $this->actingAs($secretary)->post(route('clearances.store'), $this->payload($this->photographedResident()))->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $this->assertSame('BC-'.now()->year.'-000001', BarangayClearance::query()->sole()->clearance_number);
    }

    public function test_an_unresolved_complaint_hold_blocks_issuance(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $held = $this->photographedResident();
        $this->holdResident($held);

        $this->actingAs($secretary)->postJson(route('clearances.store'), $this->payload($held))
            ->assertUnprocessable()->assertJsonPath('error.code', 'resident_on_hold');
        $this->actingAs($secretary)->postJson(route('clearances.store'), $this->payload($this->photographedResident()))->assertRedirect();

        $this->inBarangay($barangay);
        $this->assertSame(1, BarangayClearance::query()->count());
    }

    public function test_the_hold_is_not_checked_when_its_flag_is_off(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->turnOff(FeatureFlag::UnresolvedComplaintHold, $barangay);
        $held = $this->photographedResident();
        $this->holdResident($held);

        $this->actingAs($secretary)->post(route('clearances.store'), $this->payload($held))->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $this->assertSame($held->id, BarangayClearance::query()->sole()->resident_id);
    }

    public function test_residents_without_photos_get_clearances_when_photos_are_not_required(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->turnOff(FeatureFlag::IssuanceRequiresPhoto, $barangay);

        $this->actingAs($secretary)->post(route('clearances.store'), $this->payload(Resident::factory()->create()))->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $this->assertSame(1, BarangayClearance::query()->count());
    }

    public function test_or_numbers_are_unique_across_documents_that_are_not_cancelled(): void
    {
        [$secretary] = $this->residentDesk();
        $resident = $this->photographedResident();
        IdCard::factory()->create(['resident_id' => $resident->id, 'or_number' => 'OR-5']);
        BarangayClearance::factory()->cancelled()->create(['resident_id' => $resident->id, 'or_number' => 'OR-6']);

        $this->actingAs($secretary)->post(route('clearances.store'), $this->payload($resident, ['or_number' => 'OR-5']))->assertSessionHasErrors('or_number');
        $this->actingAs($secretary)->post(route('clearances.store'), $this->payload($resident, ['or_number' => 'OR-6']))->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('clearances.store'), $this->payload($resident, ['or_number' => 'OR-6']))->assertSessionHasErrors('or_number');
        $this->actingAs($secretary)->post(route('id-cards.store'), ['resident_id' => $resident->id, 'or_number' => 'OR-6'])->assertSessionHasErrors('or_number');
    }

    public function test_pending_clearances_can_be_edited_but_released_and_cancelled_ones_cannot(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $pending = BarangayClearance::factory()->create();
        $released = BarangayClearance::factory()->released()->create();
        $cancelled = BarangayClearance::factory()->cancelled()->create();

        $this->actingAs($secretary)->put(route('clearances.update', $pending), [
            'clearance_type_id' => $pending->clearance_type_id,
            'purpose_id' => $pending->purpose_id,
            'or_number' => 'OR-11',
            'amount' => '75.50',
        ])->assertSessionHasNoErrors();

        foreach ([$released, $cancelled] as $locked) {
            $this->actingAs($secretary)->putJson(route('clearances.update', $locked), [
                'clearance_type_id' => $locked->clearance_type_id,
                'purpose_id' => $locked->purpose_id,
                'amount' => '1.00',
            ])->assertUnprocessable()->assertJsonPath('error.code', 'document_not_editable');
        }

        $this->inBarangay($barangay);
        $this->assertSame('75.50', $pending->refresh()->amount);
        $this->assertSame('OR-11', $pending->or_number);
        $this->assertSame('0.00', $released->refresh()->amount);
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::ClearanceUpdated)->count());
    }

    public function test_bulk_release_and_cancel_are_all_or_nothing_and_cancel_needs_a_reason(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $pending = BarangayClearance::factory()->count(2)->create();
        $released = BarangayClearance::factory()->released()->create();
        $ids = $pending->pluck('id')->all();

        $this->actingAs($secretary)->post(route('clearances.release'), ['ids' => [...$ids, $released->id]])->assertRedirect();
        $this->actingAs($secretary)->post(route('clearances.cancel'), ['ids' => [...$ids, $released->id], 'reason' => 'Duplicate'])->assertRedirect();
        $this->actingAs($secretary)->post(route('clearances.cancel'), ['ids' => $ids])->assertSessionHasErrors('reason');

        $this->inBarangay($barangay);
        $this->assertSame(2, BarangayClearance::query()->where('status', IssuedDocumentStatus::Issued)->count());
        $this->assertFalse(AuditEvent::query()->whereIn('action', [AuditAction::ClearanceReleased, AuditAction::ClearanceCancelled])->exists());

        $this->actingAs($secretary)->post(route('clearances.release'), ['ids' => [$ids[0]]])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('clearances.cancel'), ['ids' => [$ids[1]], 'reason' => 'Wrong purpose'])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $first = BarangayClearance::query()->findOrFail($ids[0]);
        $second = BarangayClearance::query()->findOrFail($ids[1]);
        $this->assertSame(IssuedDocumentStatus::Released, $first->status);
        $this->assertSame($secretary->id, $first->released_by);
        $this->assertSame(IssuedDocumentStatus::Cancelled, $second->status);
        $this->assertSame('Wrong purpose', $second->cancel_reason);
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::ClearanceReleased)->count());
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::ClearanceCancelled)->count());
    }

    public function test_printing_counts_prints_marks_reprints_and_audits_each_print(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $clearance = BarangayClearance::factory()->create(['resident_id' => $this->photographedResident()->id, 'amount' => '100.00', 'doc_stamp' => '30.00']);

        $this->actingAs($secretary)->get(route('clearances.print', $clearance))->assertInertia(fn (Assert $page) => $page
            ->component('clearances/Print')
            ->where('document.print_count', 1)
            ->where('document.is_reprint', false)
            ->where('document.total', '130.00')
            ->where('document.qr', fn (?string $qr) => is_string($qr) && str_starts_with($qr, 'data:image/svg+xml;base64,'))
            ->where('certifier.signature', null));
        $this->actingAs($secretary)->get(route('clearances.print', $clearance))->assertInertia(fn (Assert $page) => $page
            ->where('document.print_count', 2)
            ->where('document.is_reprint', true));

        $this->inBarangay($barangay);
        $this->assertSame(2, $clearance->refresh()->print_count);
        $this->assertSame(2, AuditEvent::query()->where('action', AuditAction::ClearancePrinted)->count());

        $this->turnOff(FeatureFlag::ClearanceQrCode, $barangay);
        $this->turnOff(FeatureFlag::ClearanceDocStamp, $barangay);
        $this->actingAs($secretary)->get(route('clearances.print', $clearance))->assertInertia(fn (Assert $page) => $page
            ->where('document.qr', null)
            ->where('document.doc_stamp', null)
            ->where('document.total', '100.00'));

        $cancelled = BarangayClearance::factory()->cancelled()->create();
        $this->actingAs($secretary)->get(route('clearances.print', $cancelled))->assertUnprocessable();
    }

    public function test_the_captain_signature_prints_only_when_enabled_and_the_signatory_matches_the_snapshot(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        Storage::fake('local');
        Storage::disk('local')->put('signatures/captain.png', 'signature-bytes');
        Signatory::query()->create(['office' => SignatoryOffice::PunongBarangay, 'name' => 'Pedro Reyes', 'title' => 'Punong Barangay', 'signature_path' => 'signatures/captain.png']);
        $matching = BarangayClearance::factory()->create(['certified_by_name' => 'Pedro Reyes']);
        $former = BarangayClearance::factory()->create(['certified_by_name' => 'Former Captain']);

        $this->actingAs($secretary)->get(route('clearances.print', $matching))->assertInertia(fn (Assert $page) => $page->where('certifier.signature', null));

        $this->turnOn(FeatureFlag::CaptainSignatureOnClearance, $barangay);
        $this->actingAs($secretary)->get(route('clearances.print', $matching))
            ->assertInertia(fn (Assert $page) => $page->where('certifier.signature', fn (?string $uri) => is_string($uri) && str_starts_with($uri, 'data:')));
        $this->actingAs($secretary)->get(route('clearances.print', $former))->assertInertia(fn (Assert $page) => $page->where('certifier.signature', null));
    }

    public function test_without_the_release_step_issuing_releases_immediately_and_release_is_unavailable(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->turnOff(FeatureFlag::DocumentReleaseStep, $barangay);

        $this->actingAs($secretary)->post(route('clearances.store'), $this->payload($this->photographedResident()))->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $clearance = BarangayClearance::query()->sole();
        $this->assertSame(IssuedDocumentStatus::Released, $clearance->status);
        $this->assertSame($secretary->id, $clearance->released_by);
        $this->assertTrue($clearance->released_at?->equalTo($clearance->issued_at));

        $this->actingAs($secretary)->postJson(route('clearances.release'), ['ids' => [$clearance->id]])->assertNotFound();
    }

    public function test_doc_stamp_and_cedula_fields_are_ignored_when_their_flags_are_off(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->turnOff(FeatureFlag::ClearanceDocStamp, $barangay);
        $this->turnOff(FeatureFlag::ClearanceCtcReference, $barangay);

        $this->actingAs($secretary)->post(route('clearances.store'), $this->payload($this->photographedResident(), ['ctc_number' => 'CTC-1']))->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $clearance = BarangayClearance::query()->sole();
        $this->assertSame('0.00', $clearance->doc_stamp);
        $this->assertNull($clearance->ctc_number);
    }

    public function test_clearances_of_other_barangays_are_not_found(): void
    {
        [$admin] = $this->residentDesk(SystemRole::BarangayAdmin);
        [, $other] = $this->residentDesk();
        $outsider = BarangayClearance::factory()->create(['resident_id' => Resident::factory()->create(['barangay_id' => $other->id])->id]);
        $this->inBarangay($admin->barangay);

        $this->actingAs($admin)->get(route('clearances.edit', $outsider))->assertNotFound();
        $this->actingAs($admin)->get(route('clearances.print', $outsider))->assertNotFound();
        $this->actingAs($admin)->post(route('clearances.release'), ['ids' => [$outsider->id]])->assertSessionHasErrors('ids.0');
        $this->actingAs($admin)->get(route('clearances.index'))->assertInertia(fn (Assert $page) => $page->where('clearances.meta.total', 0));
    }

    public function test_permissions_and_the_module_flag_are_enforced(): void
    {
        [$viewer, $barangay] = $this->residentDesk(SystemRole::Viewer);
        $clearance = BarangayClearance::factory()->create();
        $clerk = $this->barangayUser(SystemRole::Clerk, $barangay);
        $treasurer = $this->barangayUser(SystemRole::Treasurer, $barangay);

        $this->actingAs($viewer)->get(route('clearances.index'))->assertOk();
        $this->actingAs($viewer)->get(route('clearances.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('clearances.print', $clearance))->assertForbidden();
        $this->actingAs($clerk)->get(route('clearances.create'))->assertOk();
        $this->actingAs($clerk)->post(route('clearances.release'), ['ids' => [$clearance->id]])->assertForbidden();
        $this->actingAs($clerk)->post(route('clearances.cancel'), ['ids' => [$clearance->id], 'reason' => 'x'])->assertForbidden();
        $this->actingAs($treasurer)->get(route('clearances.edit', $clearance))->assertForbidden();

        $this->turnOff(FeatureFlag::BarangayClearance, $barangay);
        $this->actingAs($viewer)->get(route('clearances.index'))->assertNotFound();
        $this->actingAs($clerk)->postJson(route('clearances.store'), ['resident_id' => $clearance->resident_id])->assertNotFound();
        $this->actingAs($clerk)->post(route('clearances.store'), ['resident_id' => $clearance->resident_id])->assertRedirect();
    }

    public function test_the_list_filters_and_totals_exclude_cancelled_clearances(): void
    {
        [$viewer] = $this->residentDesk(SystemRole::Viewer);
        $juan = Resident::factory()->named('Dela Cruz', 'Juan')->create();
        BarangayClearance::factory()->create(['resident_id' => $juan->id, 'amount' => '50.00', 'doc_stamp' => '10.00']);
        BarangayClearance::factory()->released()->create(['amount' => '25.00']);
        BarangayClearance::factory()->cancelled()->create(['amount' => '999.00']);

        $this->actingAs($viewer)->get(route('clearances.index'))->assertInertia(fn (Assert $page) => $page
            ->component('clearances/Index')
            ->where('clearances.meta.total', 3)
            ->where('totals.amount', '75.00')
            ->where('totals.doc_stamp', '10.00')
            ->where('totals.total', '85.00'));
        $this->actingAs($viewer)->get(route('clearances.index', ['status' => 'issued']))
            ->assertInertia(fn (Assert $page) => $page->where('clearances.meta.total', 1));
        $this->actingAs($viewer)->get(route('clearances.index', ['search' => 'Dela Cruz, Juan']))
            ->assertInertia(fn (Assert $page) => $page->where('clearances.meta.total', 1)->where('clearances.data.0.resident.full_name', 'Dela Cruz, Juan'));
    }

    public function test_the_resident_page_lists_clearances_and_cancelling_there_keeps_the_record(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $resident = $this->photographedResident();
        $clearance = BarangayClearance::factory()->create(['resident_id' => $resident->id]);
        BarangayClearance::factory()->create();

        $this->actingAs($secretary)->get(route('residents.show', $resident))
            ->assertInertia(fn (Assert $page) => $page->has('clearances', 1)->where('clearances.0.id', $clearance->id));

        $this->actingAs($secretary)->post(route('clearances.cancel'), ['ids' => [$clearance->id], 'reason' => 'Requested by resident'])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $this->assertSame(IssuedDocumentStatus::Cancelled, $clearance->refresh()->status);
        $this->assertSame(1, BarangayClearance::query()->whereKey($clearance->id)->count());
    }

    public function test_the_resident_page_hides_clearances_when_the_module_is_off(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->turnOff(FeatureFlag::BarangayClearance, $barangay);
        $resident = $this->photographedResident();

        $this->actingAs($secretary)->get(route('residents.show', $resident))
            ->assertInertia(fn (Assert $page) => $page->where('clearances', null));
    }
}
