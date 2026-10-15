<?php

namespace Tests\Feature\Residents;

use App\Domain\Audit\AuditRedactor;
use App\Enums\AuditAction;
use App\Enums\LookupType;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\Resident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class ResidentRegistryTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    public function test_registering_a_resident_allocates_a_number_normalizes_input_and_audits(): void
    {
        [$secretary, $barangay, $purok] = $this->residentDesk();
        $sector = $this->lookup(LookupType::Sector);

        $this->actingAs($secretary)
            ->post(route('residents.store'), $this->residentPayload($purok, [
                'national_id' => '1234-5678-9012',
                'sector_ids' => [$sector->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->inBarangay($barangay);
        $resident = Resident::query()->with('sectors')->sole();

        $this->assertSame('RES-000001', $resident->resident_number);
        $this->assertSame('Dela Cruz, Juan Santos', $resident->full_name);
        $this->assertSame('Dela Cruz', $resident->last_name);
        $this->assertSame('09171234567', $resident->mobile_number);
        $this->assertFalse($resident->is_pwd);
        $this->assertFalse($resident->is_incomplete);
        $this->assertSame($secretary->id, $resident->created_by);
        $this->assertSame([$sector->id], $resident->sectors->pluck('id')->all());

        $audit = AuditEvent::query()->where('action', AuditAction::ResidentCreated)->sole();
        $this->assertSame((string) $resident->id, $audit->subject_id);
        $this->assertSame($barangay->id, $audit->barangay_id);
        $this->assertSame(AuditRedactor::REDACTED, $audit->changes['after']['national_id'] ?? null);
        $this->assertSame([$sector->id], $audit->changes['after']['sector_ids'] ?? null);
    }

    public function test_last_and_first_names_are_required_and_mobile_numbers_are_validated(): void
    {
        [$secretary, , $purok] = $this->residentDesk();

        $this->actingAs($secretary)
            ->post(route('residents.store'), $this->residentPayload($purok, [
                'last_name' => ' ',
                'first_name' => '',
                'mobile_number' => '12345',
                'facebook_url' => 'javascript:alert(1)',
            ]))
            ->assertSessionHasErrors(['last_name', 'first_name', 'mobile_number', 'facebook_url']);
    }

    public function test_editing_keeps_creation_metadata_and_records_only_changes(): void
    {
        [$secretary, $barangay, $purok] = $this->residentDesk();
        $clerk = $this->barangayUser(SystemRole::Clerk, $barangay);
        $this->inBarangay($barangay);
        $resident = Resident::factory()->create(['created_by' => $secretary->id, 'purok_id' => $purok->id, 'created_at' => '2025-01-02 03:04:05']);

        $this->actingAs($clerk)
            ->put(route('residents.update', $resident), $this->residentPayload($purok, [
                'last_name' => $resident->last_name,
                'first_name' => 'Pedro',
                'middle_name' => null,
                'birthdate' => $resident->birthdate?->toDateString(),
                'gender' => $resident->gender?->value,
                'civil_status' => 'single',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('residents.edit', $resident));

        $resident->refresh();
        $this->assertSame($secretary->id, $resident->created_by);
        $this->assertSame('2025-01-02 03:04:05', $resident->created_at?->format('Y-m-d H:i:s'));
        $this->assertSame($clerk->id, $resident->updated_by);
        $this->assertSame('Pedro', $resident->first_name);

        $audit = AuditEvent::query()->where('action', AuditAction::ResidentUpdated)->sole();
        $this->assertSame('Pedro', $audit->changes['after']['first_name'] ?? null);
        $this->assertArrayNotHasKey('last_name', $audit->changes['after'] ?? []);
    }

    public function test_spouse_links_are_symmetric_and_old_back_links_are_cleared(): void
    {
        [$secretary, $barangay, $purok] = $this->residentDesk();
        [$ana, $ben, $carla] = Resident::factory()->count(3)->create(['purok_id' => $purok->id])->all();

        $payloadFor = fn (Resident $resident, ?int $spouseId): array => $this->residentPayload($purok, [
            'last_name' => $resident->last_name,
            'first_name' => $resident->first_name,
            'gender' => $resident->gender?->value,
            'spouse_id' => $spouseId,
        ]);

        $this->actingAs($secretary)->put(route('residents.update', $ana), $payloadFor($ana, $ben->id))->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $this->assertSame($ben->id, $ana->refresh()->spouse_id);
        $this->assertSame($ana->id, $ben->refresh()->spouse_id);

        $this->actingAs($secretary)->put(route('residents.update', $ana), $payloadFor($ana, $carla->id))->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $this->assertSame($carla->id, $ana->refresh()->spouse_id);
        $this->assertSame($ana->id, $carla->refresh()->spouse_id);
        $this->assertNull($ben->refresh()->spouse_id);

        $this->actingAs($secretary)->put(route('residents.update', $ben), $payloadFor($ben, $carla->id))->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $this->assertSame($ben->id, $carla->refresh()->spouse_id);
        $this->assertNull($ana->refresh()->spouse_id);

        $this->actingAs($secretary)->put(route('residents.update', $carla), $payloadFor($carla, null))->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $this->assertNull($carla->refresh()->spouse_id);
        $this->assertNull($ben->refresh()->spouse_id);
    }

    public function test_a_resident_cannot_be_their_own_spouse(): void
    {
        [$secretary, , $purok] = $this->residentDesk();
        $resident = Resident::factory()->create(['purok_id' => $purok->id]);

        $this->actingAs($secretary)
            ->put(route('residents.update', $resident), $this->residentPayload($purok, ['spouse_id' => $resident->id]))
            ->assertSessionHasErrors('spouse_id');
    }

    public function test_duplicates_warn_until_confirmed_and_ignore_removed_residents(): void
    {
        [$secretary, $barangay, $purok] = $this->residentDesk();
        $existing = Resident::factory()->named('Dela Cruz', 'Juan')->create(['birthdate' => '1980-05-01']);

        $this->actingAs($secretary)
            ->post(route('residents.store'), $this->residentPayload($purok, ['last_name' => 'DELA  CRUZ', 'first_name' => 'juan']))
            ->assertSessionHasErrors('duplicate');

        $this->actingAs($secretary)
            ->post(route('residents.store'), $this->residentPayload($purok, ['confirm_duplicate' => true]))
            ->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $this->assertSame(2, Resident::query()->count());

        Resident::query()->whereKeyNot($existing->id)->firstOrFail()->delete();
        $existing->delete();

        $this->actingAs($secretary)
            ->post(route('residents.store'), $this->residentPayload($purok))
            ->assertSessionHasNoErrors();
    }

    public function test_duplicates_in_another_barangay_do_not_warn(): void
    {
        [$secretary, , $purok] = $this->residentDesk();
        [, $other] = $this->residentDesk();
        Resident::factory()->named('Dela Cruz', 'Juan')->create(['barangay_id' => $other->id, 'birthdate' => '1980-05-01']);
        $this->inBarangay($secretary->barangay);

        $this->actingAs($secretary)
            ->post(route('residents.store'), $this->residentPayload($purok))
            ->assertSessionHasNoErrors();
    }

    public function test_soft_delete_records_actor_hides_the_resident_and_can_be_restored(): void
    {
        [$admin, $barangay, $purok] = $this->residentDesk(SystemRole::BarangayAdmin);
        $resident = Resident::factory()->named('Dela Cruz', 'Juan')->create(['purok_id' => $purok->id]);

        $this->actingAs($admin)->delete(route('residents.destroy', $resident))->assertRedirect(route('residents.index'));

        $this->inBarangay($barangay);
        $removed = Resident::query()->withTrashed()->findOrFail($resident->id);
        $this->assertNotNull($removed->deleted_at);
        $this->assertSame($admin->id, $removed->deleted_by);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::ResidentDeleted)->where('subject_id', (string) $resident->id)->exists());

        $this->actingAs($admin)->getJson(route('residents.search', ['q' => 'Dela Cruz']))->assertJsonCount(0, 'data');
        $this->actingAs($admin)->get(route('residents.show', $resident))->assertNotFound();

        $this->actingAs($admin)->post(route('residents.restore', $resident))->assertRedirect(route('residents.show', $resident));
        $this->inBarangay($barangay);
        $this->assertNull($resident->refresh()->deleted_at);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::ResidentRestored)->exists());
    }

    public function test_quick_add_creates_an_incomplete_numbered_resident(): void
    {
        [$clerk, $barangay] = $this->residentDesk(SystemRole::Clerk);

        $this->actingAs($clerk)
            ->postJson(route('residents.quick-add'), ['last_name' => 'Reyes', 'first_name' => 'Maria'])
            ->assertCreated()
            ->assertJsonPath('data.full_name', 'Reyes, Maria')
            ->assertJsonPath('data.resident_number', 'RES-000001');

        $this->inBarangay($barangay);
        $this->assertTrue(Resident::query()->sole()->is_incomplete);

        $this->actingAs($clerk)
            ->postJson(route('residents.quick-add'), ['last_name' => 'reyes', 'first_name' => 'MARIA'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duplicate');
    }
}
