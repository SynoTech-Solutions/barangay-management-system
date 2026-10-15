<?php

namespace Tests\Feature\Residents;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Purok;
use App\Models\Resident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class PurokManagementTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    public function test_purok_names_are_unique_per_barangay_ignoring_case_and_spacing(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        [, $other] = $this->residentDesk();
        Purok::factory()->create(['barangay_id' => $other->id, 'name' => 'Purok Siete']);

        $this->actingAs($secretary)->post(route('puroks.store'), ['name' => ' Purok   Siete '])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->post(route('puroks.store'), ['name' => 'purok siete'])->assertRedirect();

        $this->inBarangay($barangay);
        $this->assertSame(['Purok Siete'], Purok::query()->where('name', 'ilike', '%siete%')->pluck('name')->all());
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::PurokCreated)->count());
    }

    public function test_referenced_puroks_are_deactivated_instead_of_deleted(): void
    {
        [$secretary, $barangay, $purok] = $this->residentDesk();
        $unused = Purok::factory()->create();
        Resident::factory()->create(['purok_id' => $purok->id]);

        $this->actingAs($secretary)->delete(route('puroks.destroy', $purok))->assertRedirect();
        $this->actingAs($secretary)->delete(route('puroks.destroy', $unused))->assertRedirect();

        $this->inBarangay($barangay);
        $this->assertFalse($purok->refresh()->is_active);
        $this->assertNull(Purok::query()->find($unused->id));
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::PurokDeactivated)->exists());
    }

    public function test_inactive_puroks_cannot_be_chosen_for_new_residents(): void
    {
        [$secretary, , $purok] = $this->residentDesk();
        $purok->update(['is_active' => false]);

        $this->actingAs($secretary)->post(route('residents.store'), $this->residentPayload($purok))->assertSessionHasErrors('purok_id');
    }
}
