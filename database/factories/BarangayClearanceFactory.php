<?php

namespace Database\Factories;

use App\Enums\IssuedDocumentStatus;
use App\Enums\LookupType;
use App\Models\BarangayClearance;
use App\Models\LookupValue;
use App\Models\Resident;
use Database\Factories\Concerns\RecordsOfficialReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BarangayClearance>
 */
class BarangayClearanceFactory extends Factory
{
    use RecordsOfficialReceipt;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'barangay_id' => fn (array $attributes) => Resident::acrossBarangays()->whereKey($attributes['resident_id'])->value('barangay_id'),
            'clearance_number' => fake()->unique()->numerify('BC-T######'),
            'control_number' => fake()->unique()->numerify('BCC-T######'),
            'status' => IssuedDocumentStatus::Issued,
            'clearance_type_id' => LookupValue::factory()->ofType(LookupType::ClearanceType),
            'purpose_id' => LookupValue::factory()->ofType(LookupType::ClearancePurpose),
            'amount' => '0.00',
            'doc_stamp' => '0.00',
            'expires_on' => now()->endOfYear()->toDateString(),
            'issued_at' => now(),
        ];
    }

    public function released(): static
    {
        return $this->state(fn () => ['status' => IssuedDocumentStatus::Released, 'released_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => IssuedDocumentStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => 'Wrong entry']);
    }
}
