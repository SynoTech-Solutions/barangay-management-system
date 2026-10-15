<?php

namespace Tests\Feature\Residents;

use App\Enums\LookupType;
use App\Models\FamilyMember;
use App\Models\Resident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class ResidentPagesTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    public function test_resident_screens_render_with_their_data(): void
    {
        [$secretary, , $purok] = $this->residentDesk();
        $this->lookup(LookupType::Sector);
        $resident = Resident::factory()->create(['purok_id' => $purok->id, 'national_id' => '1234']);
        FamilyMember::factory()->create(['resident_id' => $resident->id, 'full_name' => 'Santos, Ana']);

        $this->actingAs($secretary)->get(route('residents.create'))->assertInertia(fn (Assert $page) => $page
            ->component('residents/Form')
            ->where('resident', null)
            ->where('options.puroks.0.value', $purok->id)
            ->where('limits.photo_max_kilobytes', 4096)
            ->has('options.lookups.sector', fn (Assert $options) => $options->each(fn (Assert $option) => $option->hasAll(['value', 'label'])))
            ->etc());

        $this->actingAs($secretary)->get(route('residents.edit', $resident))->assertInertia(fn (Assert $page) => $page
            ->component('residents/Form')
            ->where('resident.id', $resident->id)
            ->where('resident.purok', $purok->name)
            ->where('familyMembers.0.full_name', 'Santos, Ana'));

        $this->actingAs($secretary)->get(route('residents.show', $resident))->assertInertia(fn (Assert $page) => $page
            ->component('residents/Show')
            ->where('resident.national_id', '1234')
            ->has('educations')
            ->has('workHistories'));

        $this->actingAs($secretary)->get(route('residents.print', $resident))->assertInertia(fn (Assert $page) => $page
            ->component('residents/Print')
            ->where('resident.id', $resident->id)
            ->where('preparedBy', $secretary->name)
            ->has('header.barangay'));

        $this->actingAs($secretary)->get(route('puroks.index'))->assertInertia(fn (Assert $page) => $page
            ->component('residents/puroks/Index')
            ->where('puroks.0.residents_count', 1));
    }
}
