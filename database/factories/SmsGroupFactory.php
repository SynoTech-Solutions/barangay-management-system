<?php

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\SmsGroup;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsGroup>
 */
class SmsGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'name' => 'Group '.fake()->unique()->numberBetween(1, 99999),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
