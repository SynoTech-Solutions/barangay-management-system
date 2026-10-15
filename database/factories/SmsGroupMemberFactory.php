<?php

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\Resident;
use App\Models\SmsGroup;
use App\Models\SmsGroupMember;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsGroupMember>
 */
class SmsGroupMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'sms_group_id' => SmsGroup::factory(),
            'resident_id' => null,
            'mobile_number' => '+63917000'.fake()->unique()->numerify('####'),
            'label' => null,
        ];
    }

    public function forResident(Resident $resident): static
    {
        return $this->state(fn () => ['resident_id' => $resident->id, 'mobile_number' => null]);
    }
}
