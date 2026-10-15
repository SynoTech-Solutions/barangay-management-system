<?php

namespace Database\Factories;

use App\Enums\IssuedDocumentStatus;
use App\Models\IdCard;
use App\Models\Resident;
use Database\Factories\Concerns\RecordsOfficialReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdCard>
 */
class IdCardFactory extends Factory
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
            'card_number' => fake()->unique()->numerify('ID-T######'),
            'status' => IssuedDocumentStatus::Issued,
            'amount' => '0.00',
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
