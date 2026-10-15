<?php

namespace Database\Factories;

use App\Enums\IssuedDocumentStatus;
use App\Models\Business;
use App\Models\BusinessClearance;
use Database\Factories\Concerns\RecordsOfficialReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessClearance>
 */
class BusinessClearanceFactory extends Factory
{
    use RecordsOfficialReceipt;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'barangay_id' => fn (array $attributes) => Business::acrossBarangays()->whereKey($attributes['business_id'])->value('barangay_id'),
            'clearance_number' => fake()->unique()->numerify('BUC-T######'),
            'control_number' => fake()->unique()->numerify('BUCC-T######'),
            'status' => IssuedDocumentStatus::Issued,
            'business_name' => fn (array $attributes) => Business::acrossBarangays()->whereKey($attributes['business_id'])->value('name'),
            'owner_name' => 'Juan Dela Cruz',
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

    public function expiredOn(string $date): static
    {
        return $this->state(fn () => ['status' => IssuedDocumentStatus::Released, 'released_at' => now(), 'expires_on' => $date]);
    }
}
