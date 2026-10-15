<?php

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\ColorCategory;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColorCategory>
 */
class ColorCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'name' => 'Category '.fake()->unique()->numberBetween(1, 99999),
            'color_hex' => fake()->hexColor(),
            'is_active' => true,
        ];
    }
}
