<?php

namespace Database\Factories;

use App\Enums\IssuedDocumentStatus;
use App\Enums\LookupType;
use App\Models\IndigencyCertificate;
use App\Models\LookupValue;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndigencyCertificate>
 */
class IndigencyCertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'barangay_id' => fn (array $attributes) => Resident::acrossBarangays()->whereKey($attributes['resident_id'])->value('barangay_id'),
            'certificate_number' => fake()->unique()->numerify('IND-T######'),
            'status' => IssuedDocumentStatus::Issued,
            'purpose_id' => LookupValue::factory()->ofType(LookupType::CertificatePurpose),
            'amount' => '0.00',
            'issued_at' => now(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => IssuedDocumentStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => 'Wrong entry']);
    }
}
