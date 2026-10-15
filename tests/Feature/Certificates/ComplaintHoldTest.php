<?php

namespace Tests\Feature\Certificates;

use App\Enums\FeatureFlag;
use App\Enums\LookupType;
use App\Models\BarangayClearance;
use App\Models\Blotter;
use App\Models\IdCard;
use App\Models\Resident;
use App\Services\Holds\ResidentHoldChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesResidents;
use Tests\TestCase;

class ComplaintHoldTest extends TestCase
{
    use CreatesResidents, RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function clearance(Resident $resident): array
    {
        return [
            'resident_id' => $resident->id,
            'clearance_type_id' => $this->lookup(LookupType::ClearanceType)->id,
            'purpose_id' => $this->lookup(LookupType::ClearancePurpose)->id,
        ];
    }

    public function test_a_respondent_in_an_open_blotter_cannot_get_a_clearance_or_an_id_card(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $respondent = $this->photographedResident();
        Blotter::factory()->against($respondent)->create();

        $this->actingAs($secretary)->postJson(route('clearances.store'), $this->clearance($respondent))
            ->assertUnprocessable()->assertJsonPath('error.code', 'resident_on_hold');
        $this->actingAs($secretary)->postJson(route('id-cards.store'), ['resident_id' => $respondent->id])
            ->assertUnprocessable()->assertJsonPath('error.code', 'resident_on_hold');

        $this->inBarangay($barangay);
        $this->assertSame(0, BarangayClearance::query()->count());
        $this->assertSame(0, IdCard::query()->count());
    }

    public function test_complainants_and_respondents_of_resolved_or_cancelled_blotters_are_not_held(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $complainant = $this->photographedResident();
        $resolved = $this->photographedResident();
        $cancelled = $this->photographedResident();
        Blotter::factory()->filedBy($complainant)->create();
        Blotter::factory()->against($resolved)->resolved()->create();
        Blotter::factory()->against($cancelled)->cancelled()->create();

        foreach ([$complainant, $resolved, $cancelled] as $resident) {
            $this->actingAs($secretary)->post(route('clearances.store'), $this->clearance($resident))->assertSessionHasNoErrors();
        }
        $this->actingAs($secretary)->post(route('id-cards.store'), ['resident_id' => $resolved->id])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $this->assertSame(3, BarangayClearance::query()->count());
    }

    public function test_the_hold_is_ignored_when_its_flag_is_off(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->turnOff(FeatureFlag::UnresolvedComplaintHold, $barangay);
        $respondent = $this->photographedResident();
        Blotter::factory()->against($respondent)->create();

        $this->actingAs($secretary)->post(route('clearances.store'), $this->clearance($respondent))->assertSessionHasNoErrors();
        $this->actingAs($secretary)->getJson(route('residents.search', ['q' => $respondent->last_name, 'for_issuance' => 1]))
            ->assertJsonPath('data.0.on_hold', false);
    }

    public function test_the_hold_follows_the_blotter_module_flag(): void
    {
        [, $barangay] = $this->residentDesk();
        $respondent = Resident::factory()->create();
        Blotter::factory()->against($respondent)->create();

        $this->assertSame([$respondent->id], app(ResidentHoldChecker::class)->heldAmong([$respondent->id]));

        $this->turnOff(FeatureFlag::Blotter, $barangay);
        $this->assertSame([], app(ResidentHoldChecker::class)->heldAmong([$respondent->id]));
    }

    public function test_the_picker_marks_held_residents_only_for_issuance(): void
    {
        [$secretary] = $this->residentDesk();
        $held = Resident::factory()->named('Holdtest', 'Juan')->create();
        $free = Resident::factory()->named('Holdtest', 'Maria')->create();
        Blotter::factory()->against($held)->create();

        $this->actingAs($secretary)->getJson(route('residents.search', ['q' => 'Holdtest', 'for_issuance' => 1]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $held->id)
            ->assertJsonPath('data.0.on_hold', true)
            ->assertJsonPath('data.1.id', $free->id)
            ->assertJsonPath('data.1.on_hold', false);
        $this->actingAs($secretary)->getJson(route('residents.search', ['q' => 'Holdtest']))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('data.0.on_hold');
    }
}
