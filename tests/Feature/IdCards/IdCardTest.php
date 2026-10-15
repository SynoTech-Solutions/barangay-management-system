<?php

namespace Tests\Feature\IdCards;

use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Enums\IssuedDocumentStatus;
use App\Enums\SignatoryOffice;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\IdCard;
use App\Models\Resident;
use App\Models\Signatory;
use App\Services\Features\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class IdCardTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    public function test_issuing_allocates_a_number_snapshots_signatories_and_audits(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        Signatory::query()->create(['office' => SignatoryOffice::Secretary, 'name' => 'Ana Santos', 'title' => 'Barangay Secretary']);
        Signatory::query()->create(['office' => SignatoryOffice::PunongBarangay, 'name' => 'Pedro Reyes', 'title' => 'Punong Barangay']);
        $resident = $this->photographedResident();

        $this->actingAs($secretary)
            ->post(route('id-cards.store'), ['resident_id' => $resident->id, 'or_number' => 'OR-1', 'amount' => '50.00'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('id-cards.index'));

        $this->inBarangay($barangay);
        $card = IdCard::query()->sole();
        $this->assertSame('ID-'.now()->year.'-000001', $card->card_number);
        $this->assertSame(IssuedDocumentStatus::Issued, $card->status);
        $this->assertSame('Ana Santos', $card->verified_by_name);
        $this->assertSame('Pedro Reyes', $card->certified_by_name);
        $this->assertSame('50.00', $card->amount);
        $this->assertSame($secretary->id, $card->issued_by);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::IdCardIssued)->where('subject_id', (string) $card->id)->exists());

        Signatory::query()->where('office', SignatoryOffice::PunongBarangay)->update(['name' => 'New Captain']);
        $this->assertSame('Pedro Reyes', $card->refresh()->certified_by_name);
    }

    public function test_issuing_is_blocked_without_a_photo_when_required_and_for_deceased_or_removed_residents(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $noPhoto = Resident::factory()->create();
        $deceased = $this->photographedResident(['is_deceased' => true]);
        $removed = $this->photographedResident();
        $removed->delete();

        $this->actingAs($secretary)->postJson(route('id-cards.store'), ['resident_id' => $noPhoto->id])
            ->assertUnprocessable()->assertJsonPath('error.code', 'resident_photo_required');
        $this->actingAs($secretary)->postJson(route('id-cards.store'), ['resident_id' => $deceased->id])
            ->assertUnprocessable()->assertJsonPath('error.code', 'resident_deceased');
        $this->actingAs($secretary)->postJson(route('id-cards.store'), ['resident_id' => $removed->id])
            ->assertJsonValidationErrors('resident_id');

        $this->inBarangay($barangay);
        $this->assertSame(0, IdCard::query()->count());
    }

    public function test_residents_without_photos_get_cards_when_photos_are_not_required(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->turnOff(FeatureFlag::IssuanceRequiresPhoto, $barangay);
        $noPhoto = Resident::factory()->create();

        $this->actingAs($secretary)->post(route('id-cards.store'), ['resident_id' => $noPhoto->id])->assertSessionHasNoErrors()->assertRedirect();

        $this->inBarangay($barangay);
        $this->assertSame('ID-'.now()->year.'-000001', IdCard::query()->sole()->card_number);
    }

    public function test_or_numbers_are_unique_among_cards_that_are_not_cancelled(): void
    {
        [$secretary] = $this->residentDesk();
        $resident = $this->photographedResident();
        IdCard::factory()->cancelled()->create(['resident_id' => $resident->id, 'or_number' => 'OR-7']);

        $this->actingAs($secretary)->post(route('id-cards.store'), ['resident_id' => $resident->id, 'or_number' => 'OR-7'])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('id-cards.store'), ['resident_id' => $resident->id, 'or_number' => 'OR-7'])->assertSessionHasErrors('or_number');
    }

    public function test_bulk_release_rejects_the_whole_request_when_any_card_is_not_pending(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $pending = IdCard::factory()->count(2)->create();
        $cancelled = IdCard::factory()->cancelled()->create();

        $this->actingAs($secretary)
            ->post(route('id-cards.release'), ['card_ids' => [...$pending->pluck('id')->all(), $cancelled->id]])
            ->assertRedirect();

        $this->inBarangay($barangay);
        $this->assertSame(2, IdCard::query()->where('status', IssuedDocumentStatus::Issued)->count());
        $this->assertFalse(AuditEvent::query()->where('action', AuditAction::IdCardReleased)->exists());

        $this->actingAs($secretary)
            ->post(route('id-cards.release'), ['card_ids' => $pending->pluck('id')->all()])
            ->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $this->assertSame(2, IdCard::query()->where('status', IssuedDocumentStatus::Released)->whereNotNull('released_at')->count());
        $this->assertSame(2, AuditEvent::query()->where('action', AuditAction::IdCardReleased)->count());
    }

    public function test_cancel_requires_a_reason_and_locks_the_card(): void
    {
        [$admin, $barangay] = $this->residentDesk(SystemRole::BarangayAdmin);
        $card = IdCard::factory()->create();

        $this->actingAs($admin)->post(route('id-cards.cancel'), ['card_ids' => [$card->id]])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('id-cards.cancel'), ['card_ids' => [$card->id], 'reason' => 'Misspelled name'])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $card->refresh();
        $this->assertSame(IssuedDocumentStatus::Cancelled, $card->status);
        $this->assertSame('Misspelled name', $card->cancel_reason);
        $this->assertSame($admin->id, $card->cancelled_by);

        $this->actingAs($admin)->putJson(route('id-cards.update', $card), ['or_number' => 'OR-9'])->assertUnprocessable()->assertJsonPath('error.code', 'document_not_editable');
        $this->actingAs($admin)->get(route('id-cards.print', $card))->assertUnprocessable();
    }

    public function test_printing_counts_reprints_and_audits_each_print(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $card = IdCard::factory()->released()->create(['resident_id' => $this->photographedResident()->id]);

        $this->actingAs($secretary)->get(route('id-cards.print', $card))->assertInertia(fn (Assert $page) => $page
            ->component('id-cards/CardPrint')
            ->where('card.print_count', 1)
            ->where('card.valid_until', null)
            ->where('layout.pvc', false)
            ->has('resident.qr'));
        $this->actingAs($secretary)->get(route('id-cards.print', $card))->assertOk();

        $this->inBarangay($barangay);
        $this->assertSame(2, $card->refresh()->print_count);
        $this->assertSame(2, AuditEvent::query()->where('action', AuditAction::IdCardPrinted)->count());
    }

    public function test_validity_and_pvc_layout_are_independent_options(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $card = IdCard::factory()->create(['issued_at' => '2026-03-15 10:00:00']);
        app(FeatureFlags::class)->override(FeatureFlag::IdCardValidity, $barangay, true, null, null);

        $this->actingAs($secretary)->get(route('id-cards.print', $card))->assertInertia(fn (Assert $page) => $page
            ->where('card.valid_until', '2027-03-15')
            ->where('layout.pvc', false));

        app(FeatureFlags::class)->override(FeatureFlag::IdCardValidity, $barangay, false, null, null);
        app(FeatureFlags::class)->override(FeatureFlag::PvcIdPrinting, $barangay, true, null, null);

        $this->actingAs($secretary)->get(route('id-cards.print', $card))->assertInertia(fn (Assert $page) => $page
            ->where('card.valid_until', null)
            ->where('layout.pvc', true));
    }

    public function test_cards_of_other_barangays_are_not_found(): void
    {
        [$admin] = $this->residentDesk(SystemRole::BarangayAdmin);
        [, $other] = $this->residentDesk();
        $outsider = IdCard::factory()->create(['resident_id' => Resident::factory()->create(['barangay_id' => $other->id])->id]);
        $this->inBarangay($admin->barangay);

        $this->actingAs($admin)->get(route('id-cards.edit', $outsider))->assertNotFound();
        $this->actingAs($admin)->get(route('id-cards.print', $outsider))->assertNotFound();
        $this->actingAs($admin)->post(route('id-cards.release'), ['card_ids' => [$outsider->id]])->assertSessionHasErrors('card_ids.0');
    }

    public function test_permissions_and_feature_flag_are_enforced(): void
    {
        [$viewer, $barangay] = $this->residentDesk(SystemRole::Viewer);
        $card = IdCard::factory()->create();
        $clerk = $this->barangayUser(SystemRole::Clerk, $barangay);

        $this->actingAs($viewer)->get(route('id-cards.index'))->assertOk();
        $this->actingAs($viewer)->get(route('id-cards.create'))->assertForbidden();
        $this->actingAs($clerk)->post(route('id-cards.release'), ['card_ids' => [$card->id]])->assertForbidden();
        $this->actingAs($clerk)->post(route('id-cards.cancel'), ['card_ids' => [$card->id], 'reason' => 'x'])->assertForbidden();

        $this->turnOff(FeatureFlag::ResidentIdCards, $barangay);
        $this->actingAs($viewer)->get(route('id-cards.index'))->assertNotFound();
        $this->actingAs($clerk)->postJson(route('id-cards.store'), ['resident_id' => $card->resident_id])->assertNotFound();
    }

    public function test_ledger_filters_by_status_and_search(): void
    {
        [$viewer] = $this->residentDesk(SystemRole::Viewer);
        $juan = Resident::factory()->named('Dela Cruz', 'Juan')->create();
        IdCard::factory()->create(['resident_id' => $juan->id]);
        IdCard::factory()->released()->create();

        $this->actingAs($viewer)->get(route('id-cards.index', ['status' => 'issued']))
            ->assertInertia(fn (Assert $page) => $page->component('id-cards/Index')->where('cards.meta.total', 1));
        $this->actingAs($viewer)->get(route('id-cards.index', ['search' => 'Dela Cruz, Juan']))
            ->assertInertia(fn (Assert $page) => $page->where('cards.meta.total', 1)->where('cards.data.0.resident.full_name', 'Dela Cruz, Juan'));
        $this->actingAs($viewer)->get(route('id-cards.ledger.print', ['status' => 'released']))
            ->assertInertia(fn (Assert $page) => $page->component('id-cards/LedgerPrint')->where('total', 1)->where('filters.status', 'released'));
    }

    public function test_without_the_release_step_cards_are_released_on_issue_and_release_is_unavailable(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->turnOff(FeatureFlag::DocumentReleaseStep, $barangay);

        $this->actingAs($secretary)->post(route('id-cards.store'), ['resident_id' => $this->photographedResident()->id])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $card = IdCard::query()->sole();
        $this->assertSame(IssuedDocumentStatus::Released, $card->status);
        $this->assertSame($secretary->id, $card->released_by);
        $this->assertTrue($card->released_at?->equalTo($card->issued_at));

        $this->actingAs($secretary)->postJson(route('id-cards.release'), ['card_ids' => [$card->id]])->assertNotFound();
        $this->actingAs($secretary)->putJson(route('id-cards.update', $card), ['amount' => '5'])->assertUnprocessable()->assertJsonPath('error.code', 'document_not_editable');
    }
}
