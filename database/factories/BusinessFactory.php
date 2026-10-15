<?php

namespace Database\Factories;

use App\Enums\BusinessStatus;
use App\Enums\LookupType;
use App\Models\Barangay;
use App\Models\Business;
use App\Models\LookupValue;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'business_number' => fake()->unique()->numerify('BUS-T######'),
            'name' => 'Sample Store '.fake()->unique()->numberBetween(1, 999999),
            'owner_name' => 'Juan Dela Cruz',
            'category_id' => LookupValue::factory()->ofType(LookupType::BusinessCategory),
            'nature_id' => LookupValue::factory()->ofType(LookupType::BusinessNature),
            'ownership_type_id' => LookupValue::factory()->ofType(LookupType::BusinessOwnershipType),
            'status' => BusinessStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => BusinessStatus::Inactive]);
    }
}
