<?php

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\FormPicklist;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormPicklist>
 */
class FormPicklistFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'name' => 'List '.fake()->unique()->numberBetween(1, 999999),
            'is_active' => true,
        ];
    }
}
