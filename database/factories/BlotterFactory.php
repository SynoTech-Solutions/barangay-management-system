<?php

namespace Database\Factories;

use App\Enums\BlotterPartyRole;
use App\Enums\BlotterStatus;
use App\Enums\LookupType;
use App\Models\Barangay;
use App\Models\Blotter;
use App\Models\BlotterParty;
use App\Models\LookupValue;
use App\Models\Resident;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Blotter>
 */
class BlotterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'blotter_number' => fake()->unique()->numerify('BLT-T######'),
            'incident_type_id' => LookupValue::factory()->ofType(LookupType::BlotterIncidentType),
            'incident_at' => now()->subDay(),
            'place' => 'Purok 1',
            'narrative' => 'Sample narrative for testing.',
            'status' => BlotterStatus::Open,
            'reported_at' => now(),
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['status' => BlotterStatus::Resolved, 'resolved_at' => now(), 'resolution_notes' => 'Settled']);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => BlotterStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => 'Withdrawn']);
    }

    public function against(Resident $respondent): static
    {
        return $this->afterCreating(function (Blotter $blotter) use ($respondent): void {
            $party = new BlotterParty(['role' => BlotterPartyRole::Respondent, 'resident_id' => $respondent->id]);
            $party->barangay_id = $blotter->barangay_id;
            $party->blotter_id = $blotter->id;
            $party->save();
        });
    }

    public function filedBy(Resident $complainant): static
    {
        return $this->afterCreating(function (Blotter $blotter) use ($complainant): void {
            $party = new BlotterParty(['role' => BlotterPartyRole::Complainant, 'resident_id' => $complainant->id]);
            $party->barangay_id = $blotter->barangay_id;
            $party->blotter_id = $blotter->id;
            $party->save();
        });
    }
}
