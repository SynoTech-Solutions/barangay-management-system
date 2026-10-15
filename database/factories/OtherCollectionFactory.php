<?php

namespace Database\Factories;

use App\Models\CollectionType;
use App\Models\OtherCollection;
use Database\Factories\Concerns\RecordsOfficialReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OtherCollection>
 */
class OtherCollectionFactory extends Factory
{
    use RecordsOfficialReceipt;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collection_type_id' => CollectionType::factory(),
            'barangay_id' => fn (array $attributes) => CollectionType::acrossBarangays()->whereKey($attributes['collection_type_id'])->value('barangay_id'),
            'collection_number' => fake()->unique()->numerify('OC-T######'),
            'resident_id' => null,
            'payer_name' => 'Sample Payer',
            'or_number' => fake()->unique()->numerify('OR-T######'),
            'amount' => '100.00',
            'paid_on' => now()->toDateString(),
            'recorded_at' => now(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['cancelled_at' => now(), 'cancel_reason' => 'Wrong entry']);
    }
}
