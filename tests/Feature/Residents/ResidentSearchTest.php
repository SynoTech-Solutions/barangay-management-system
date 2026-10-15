<?php

namespace Tests\Feature\Residents;

use App\Enums\SystemRole;
use App\Models\Resident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class ResidentSearchTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    public function test_comma_queries_match_last_name_then_first_name(): void
    {
        [$viewer] = $this->residentDesk(SystemRole::Viewer);
        $juan = Resident::factory()->named('Dela Cruz', 'Juan')->create();
        Resident::factory()->named('Juan', 'Dela Cruz')->create();
        Resident::factory()->named('Dela Cruz', 'Pedro')->create();

        $this->actingAs($viewer)
            ->getJson(route('residents.search', ['q' => 'dela cruz, ju']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $juan->id)
            ->assertJsonPath('data.0.full_name', 'Dela Cruz, Juan');
    }

    public function test_plain_queries_match_every_word_and_resident_numbers(): void
    {
        [$viewer] = $this->residentDesk(SystemRole::Viewer);
        $juan = Resident::factory()->named('Dela Cruz', 'Juan')->create(['resident_number' => 'RES-000042']);
        Resident::factory()->named('Santos', 'Juan')->create();

        $this->actingAs($viewer)->getJson(route('residents.search', ['q' => 'juan cruz']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $juan->id);
        $this->actingAs($viewer)->getJson(route('residents.search', ['q' => 'RES-000042']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $juan->id);
        $this->actingAs($viewer)->getJson(route('residents.search', ['q' => '']))->assertJsonCount(0, 'data');
    }

    public function test_picker_excludes_deceased_unless_asked_and_other_barangays_always(): void
    {
        [$viewer] = $this->residentDesk(SystemRole::Viewer);
        [, $other] = $this->residentDesk(SystemRole::Viewer);
        Resident::factory()->named('Garcia', 'Lola')->deceased()->create(['barangay_id' => $viewer->barangay_id]);
        Resident::factory()->named('Garcia', 'Outsider')->create(['barangay_id' => $other->id]);

        $this->actingAs($viewer)->getJson(route('residents.search', ['q' => 'Garcia']))->assertJsonCount(0, 'data');
        $this->actingAs($viewer)
            ->getJson(route('residents.search', ['q' => 'Garcia', 'include_deceased' => 1]))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_deceased', true)
            ->assertJsonPath('meta.total', 1);
    }
}
