<?php

namespace Database\Factories;

use App\Enums\LookupType;
use App\Models\LookupValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LookupValue>
 */
class LookupValueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => null,
            'type' => LookupType::Sector,
            'label' => fake()->unique()->words(3, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function ofType(LookupType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }
}
