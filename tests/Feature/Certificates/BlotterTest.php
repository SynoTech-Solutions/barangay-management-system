<?php

namespace Tests\Feature\Certificates;

use App\Enums\AuditAction;
use App\Enums\BlotterPartyRole;
use App\Enums\BlotterStatus;
use App\Enums\FeatureFlag;
use App\Enums\LookupType;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\Blotter;
use App\Models\BlotterPhoto;
use App\Models\Resident;
use App\Support\StoragePaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class BlotterTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    private const string NARRATIVE = 'Confidential account of the incident.';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'incident_type_id' => $this->lookup(LookupType::BlotterIncidentType)->id,
            'incident_at' => now()->subHours(3)->format('Y-m-d\TH:i'),
            'place' => 'Purok 2 basketball court',
            'narrative' => self::NARRATIVE,
            'complainants' => [['resident_id' => Resident::factory()->create()->id]],
            'respondents' => [['name' => '  Pedro   Santos ', 'address' => 'Sample Town']],
            ...$overrides,
        ];
    }

    public function test_recording_a_blotter_with_mixed_parties_allocates_a_number_and_redacts_the_narrative(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $respondent = Resident::factory()->create();

        $this->actingAs($secretary)->post(route('blotters.store'), $this->payload([
            'respondents' => [['resident_id' => $respondent->id], ['name' => 'Pedro Santos', 'address' => 'Sample Town']],
        ]))->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $blotter = Blotter::query()->with('parties')->sole();
        $this->assertSame('BLT-'.now()->year.'-000001', $blotter->blotter_number);
        $this->assertSame(BlotterStatus::Open, $blotter->status);
        $this->assertCount(3, $blotter->parties);
        $this->assertSame(2, $blotter->parties->where('role', BlotterPartyRole::Respondent)->count());
        $this->assertSame('Pedro Santos', $blotter->parties->firstWhere('name', '!=', null)?->name);

        $event = AuditEvent::query()->where('action', AuditAction::BlotterRecorded)->sole();
        $this->assertSame('[redacted]', $event->changes['after']['narrative']);
        $this->assertSame([$respondent->id], $event->changes['after']['respondent_resident_ids']);
        $this->assertStringNotContainsString(self::NARRATIVE, (string) json_encode($event->changes));
    }

    public function test_complainants_are_required_and_each_party_needs_a_resident_or_a_name(): void
    {
        [$secretary] = $this->residentDesk();

        $this->actingAs($secretary)->post(route('blotters.store'), $this->payload(['complainants' => []]))->assertSessionHasErrors('complainants');
        $this->actingAs($secretary)->post(route('blotters.store'), $this->payload(['respondents' => [['address' => 'Somewhere']]]))
            ->assertSessionHasErrors(['respondents.0.name', 'respondents.0.resident_id']);
        $this->actingAs($secretary)->post(route('blotters.store'), $this->payload(['narrative' => '']))->assertSessionHasErrors('narrative');
    }

    public function test_editing_resolving_and_cancelling_follow_the_status_rules(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $blotter = Blotter::factory()->create();
        $other = Blotter::factory()->create();

        $this->actingAs($secretary)->put(route('blotters.update', $blotter), $this->payload(['narrative' => 'Updated account.']))->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('blotters.resolve', $blotter), ['notes' => 'Amicably settled'])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->putJson(route('blotters.update', $blotter), $this->payload())
            ->assertUnprocessable()->assertJsonPath('error.code', 'blotter_not_open');
        $this->actingAs($secretary)->postJson(route('blotters.cancel', $blotter), ['reason' => 'Late'])->assertUnprocessable();

        $this->actingAs($secretary)->post(route('blotters.cancel', $other))->assertSessionHasErrors('reason');
        $this->actingAs($secretary)->post(route('blotters.cancel', $other), ['reason' => 'Complaint withdrawn'])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $blotter->refresh();
        $this->assertSame(BlotterStatus::Resolved, $blotter->status);
        $this->assertSame('Amicably settled', $blotter->resolution_notes);
        $this->assertSame($secretary->id, $blotter->resolved_by);
        $this->assertSame('Updated account.', $blotter->narrative);
        $this->assertSame(BlotterStatus::Cancelled, $other->refresh()->status);
        $this->assertSame('Complaint withdrawn', $other->cancel_reason);

        $resolved = AuditEvent::query()->where('action', AuditAction::BlotterResolved)->sole();
        $this->assertSame('[redacted]', $resolved->changes['after']['resolution_notes']);
        $this->assertSame('[redacted]', AuditEvent::query()->where('action', AuditAction::BlotterUpdated)->sole()->changes['after']['narrative']);
    }

    public function test_photos_upload_stream_only_to_authorized_users_and_are_soft_deleted(): void
    {
        Storage::fake(StoragePaths::PRIVATE_DISK);
        [$secretary, $barangay] = $this->residentDesk();
        $blotter = Blotter::factory()->create();
        $viewer = $this->barangayUser(SystemRole::Viewer, $barangay);

        $this->actingAs($secretary)->post(route('blotters.photos.store', $blotter), ['photo' => UploadedFile::fake()->image('scene.jpg', 800, 600)])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('blotters.photos.store', $blotter), ['photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])->assertSessionHasErrors('photo');

        $this->inBarangay($barangay);
        $photo = BlotterPhoto::query()->sole();
        Storage::disk(StoragePaths::PRIVATE_DISK)->assertExists($photo->path);
        $this->assertSame('[redacted]', AuditEvent::query()->where('action', AuditAction::BlotterPhotoAdded)->sole()->changes['after']['photo_path']);

        $this->actingAs($secretary)->get(route('blotters.photos.show', ['blotter' => $blotter, 'photo' => $photo]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($viewer)->get(route('blotters.photos.show', ['blotter' => $blotter, 'photo' => $photo]))->assertForbidden();
        $otherBlotter = Blotter::factory()->create();
        $this->actingAs($secretary)->get(route('blotters.photos.show', ['blotter' => $otherBlotter, 'photo' => $photo]))->assertNotFound();

        $this->actingAs($secretary)->delete(route('blotters.photos.destroy', ['blotter' => $blotter, 'photo' => $photo]))->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $this->assertSoftDeleted($photo);
        $this->assertSame($secretary->id, BlotterPhoto::withTrashed()->findOrFail($photo->id)->deleted_by);
        Storage::disk(StoragePaths::PRIVATE_DISK)->assertExists($photo->path);
        $this->actingAs($secretary)->get(route('blotters.photos.show', ['blotter' => $blotter, 'photo' => $photo]))->assertNotFound();

        $this->turnOff(FeatureFlag::BlotterPhotoAttachment, $barangay);
        $this->actingAs($secretary)->post(route('blotters.photos.store', $blotter), ['photo' => UploadedFile::fake()->image('again.jpg')])->assertRedirect();
        $this->inBarangay($barangay);
        $this->assertSame(1, BlotterPhoto::withTrashed()->count());
    }

    public function test_tenant_isolation_for_blotters_and_photos(): void
    {
        Storage::fake(StoragePaths::PRIVATE_DISK);
        [$secretary, $barangay] = $this->residentDesk();
        [$otherSecretary, $other] = $this->residentDesk();
        $outsider = Blotter::factory()->create();
        $this->actingAs($otherSecretary)->post(route('blotters.photos.store', $outsider), ['photo' => UploadedFile::fake()->image('scene.jpg')])->assertSessionHasNoErrors();
        $photo = BlotterPhoto::query()->sole();
        $this->inBarangay($barangay);

        $this->actingAs($secretary)->get(route('blotters.show', $outsider))->assertNotFound();
        $this->actingAs($secretary)->get(route('blotters.edit', $outsider))->assertNotFound();
        $this->actingAs($secretary)->post(route('blotters.resolve', $outsider))->assertNotFound();
        $this->actingAs($secretary)->get(route('blotters.photos.show', ['blotter' => $outsider, 'photo' => $photo]))->assertNotFound();
        $this->actingAs($secretary)->get(route('blotters.index'))->assertInertia(fn (Assert $page) => $page->where('blotters.meta.total', 0));
        $this->assertNotNull($other->id);
    }

    public function test_only_blotter_roles_see_blotters_and_the_list_hides_the_narrative(): void
    {
        [$clerk, $barangay] = $this->residentDesk(SystemRole::Clerk);
        $blotter = Blotter::factory()->create();
        $viewer = $this->barangayUser(SystemRole::Viewer, $barangay);
        $treasurer = $this->barangayUser(SystemRole::Treasurer, $barangay);

        $this->actingAs($clerk)->get(route('blotters.index'))->assertInertia(fn (Assert $page) => $page
            ->component('blotters/Index')
            ->where('blotters.data.0.id', $blotter->id)
            ->missing('blotters.data.0.narrative'));
        $this->actingAs($clerk)->get(route('blotters.show', $blotter))->assertInertia(fn (Assert $page) => $page->where('blotter.narrative', 'Sample narrative for testing.'));
        $this->actingAs($clerk)->post(route('blotters.resolve', $blotter))->assertForbidden();
        $this->actingAs($viewer)->get(route('blotters.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('blotters.show', $blotter))->assertForbidden();
        $this->actingAs($treasurer)->get(route('blotters.show', $blotter))->assertForbidden();

        $resident = Resident::factory()->create();
        $this->actingAs($viewer)->get(route('residents.show', $resident))->assertInertia(fn (Assert $page) => $page->where('blotters', null));

        $this->turnOff(FeatureFlag::Blotter, $barangay);
        $this->actingAs($clerk)->get(route('blotters.index'))->assertNotFound();
        $this->actingAs($clerk)->post(route('blotters.store'), [])->assertRedirect();
    }

    public function test_the_resident_page_lists_blotters_with_the_residents_role(): void
    {
        [$secretary] = $this->residentDesk();
        $resident = Resident::factory()->create();
        $asRespondent = Blotter::factory()->against($resident)->create();
        $asComplainant = Blotter::factory()->filedBy($resident)->resolved()->create();
        Blotter::factory()->create();

        $this->actingAs($secretary)->get(route('residents.show', $resident))->assertInertia(fn (Assert $page) => $page
            ->has('blotters', 2)
            ->where('blotters.0.id', $asComplainant->id)
            ->where('blotters.0.role', 'complainant')
            ->where('blotters.1.id', $asRespondent->id)
            ->where('blotters.1.role', 'respondent')
            ->missing('blotters.0.narrative'));
    }
}
