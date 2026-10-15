<?php

namespace Database\Factories;

use App\Enums\LookupType;
use App\Models\FamilyMember;
use App\Models\LookupValue;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilyMember>
 */
class FamilyMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'barangay_id' => fn (array $attributes) => Resident::acrossBarangays()->whereKey($attributes['resident_id'])->value('barangay_id'),
            'relationship_id' => LookupValue::factory()->ofType(LookupType::Relationship),
            'full_name' => fake()->name(),
            'birthdate' => null,
        ];
    }
}
