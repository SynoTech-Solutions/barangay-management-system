<?php

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\CollectionType;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionType>
 */
class CollectionTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'name' => 'Collection type '.fake()->unique()->numberBetween(1, 999999),
            'is_active' => true,
        ];
    }
}
