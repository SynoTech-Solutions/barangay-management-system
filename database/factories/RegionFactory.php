<?php

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    private const int FIRST_UNUSED_REGION = 20;

    private const int LAST_REGION = 99;

    private const string REGION_SUFFIX = '00000000';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'psgc_code' => fake()->unique()->numberBetween(self::FIRST_UNUSED_REGION, self::LAST_REGION).self::REGION_SUFFIX,
            'name' => 'Region '.fake()->unique()->word(),
        ];
    }
}
