<?php

namespace Database\Factories;

use App\Enums\LookupType;
use App\Models\LookupValue;
use App\Models\Resident;
use App\Models\ResidentWorkHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResidentWorkHistory>
 */
class ResidentWorkHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'barangay_id' => fn (array $attributes) => Resident::acrossBarangays()->whereKey($attributes['resident_id'])->value('barangay_id'),
            'company_id' => LookupValue::factory()->ofType(LookupType::Company),
            'position_id' => LookupValue::factory()->ofType(LookupType::Position),
            'employment_status_id' => LookupValue::factory()->ofType(LookupType::EmploymentStatus),
            'started_on' => null,
            'ended_on' => null,
            'is_current' => false,
        ];
    }
}
