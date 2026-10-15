<?php

namespace Database\Factories;

use App\Models\Municipality;
use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Municipality>
 */
class MunicipalityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'province_id' => Province::factory(),
            'region_id' => fn (array $attributes): int => Province::query()->whereKey($attributes['province_id'])->value('region_id'),
            'psgc_code' => fake()->unique()->numerify('9######000'),
            'name' => fake()->unique()->city(),
            'is_city' => false,
        ];
    }
}
