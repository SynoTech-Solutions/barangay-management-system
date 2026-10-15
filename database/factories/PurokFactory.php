<?php

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\Purok;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purok>
 */
class PurokFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'name' => 'Purok '.fake()->unique()->numberBetween(1, 99999),
            'is_active' => true,
        ];
    }
}
