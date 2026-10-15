<?php

namespace Database\Factories;

use App\Domain\Cedula\CommunityTaxCalculator;
use App\Domain\Cedula\IndividualTaxInput;
use App\Enums\IssuedDocumentStatus;
use App\Models\Cedula;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cedula>
 */
class CedulaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $breakdown = CommunityTaxCalculator::individual(new IndividualTaxInput((int) now()->year, false, 0, 0, 0), now(), false)->toArray();

        return [
            'resident_id' => Resident::factory(),
            'barangay_id' => fn (array $attributes) => Resident::acrossBarangays()->whereKey($attributes['resident_id'])->value('barangay_id'),
            'cedula_number' => fake()->unique()->numerify('CTC-T######'),
            'tax_year' => now()->year,
            'status' => IssuedDocumentStatus::Issued,
            'place_of_issue' => 'Barangay Uno, Sample Town',
            'basic_tax' => $breakdown['basic'],
            'business_tax' => $breakdown['business'],
            'salary_tax' => $breakdown['salary'],
            'real_property_tax' => $breakdown['real_property'],
            'additional_tax' => $breakdown['additional'],
            'interest' => $breakdown['interest'],
            'amount' => $breakdown['total'],
            'breakdown' => $breakdown,
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
