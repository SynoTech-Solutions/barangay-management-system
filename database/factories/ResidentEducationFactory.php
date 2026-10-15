<?php

namespace Database\Factories;

use App\Enums\LookupType;
use App\Models\LookupValue;
use App\Models\Resident;
use App\Models\ResidentEducation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResidentEducation>
 */
class ResidentEducationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'barangay_id' => fn (array $attributes) => Resident::acrossBarangays()->whereKey($attributes['resident_id'])->value('barangay_id'),
            'school_id' => LookupValue::factory()->ofType(LookupType::School),
            'course_id' => LookupValue::factory()->ofType(LookupType::Course),
            'started_on' => null,
            'ended_on' => null,
        ];
    }
}
