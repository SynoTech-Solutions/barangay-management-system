<?php

namespace Database\Factories;

use App\Enums\BarangayStatus;
use App\Models\Barangay;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Barangay>
 */
class BarangayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'municipality_id' => Municipality::factory(),
            'psgc_code' => fake()->unique()->numerify('9#########'),
            'name' => 'Barangay '.fake()->unique()->lastName(),
            'status' => BarangayStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => BarangayStatus::Suspended]);
    }
}
