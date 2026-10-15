<?php

namespace Tests\Concerns;

use App\Enums\FeatureFlag;
use App\Enums\LookupType;
use App\Enums\SystemRole;
use App\Models\Barangay;
use App\Models\LookupValue;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use App\Services\Features\FeatureFlags;

trait CreatesResidents
{
    /**
     * @return array{0: User, 1: Barangay, 2: Purok}
     */
    protected function residentDesk(SystemRole $role = SystemRole::Secretary, ?Barangay $barangay = null): array
    {
        $user = $this->barangayUser($role, $barangay);
        $barangay = $user->barangay;
        $this->assertNotNull($barangay);
        $this->inBarangay($barangay);

        return [$user, $barangay, Purok::factory()->create(['barangay_id' => $barangay->id])];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function residentPayload(Purok $purok, array $overrides = []): array
    {
        return [
            'last_name' => '  Dela   Cruz ',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'birthdate' => '1980-05-01',
            'gender' => 'male',
            'civil_status' => 'married',
            'purok_id' => $purok->id,
            'mobile_number' => '0917 123 4567',
            ...$overrides,
        ];
    }

    protected function lookup(LookupType $type, string $label = 'Sample value'): LookupValue
    {
        return LookupValue::factory()->ofType($type)->create(['label' => $label.' '.fake()->unique()->numberBetween(1, 999999)]);
    }

    protected function turnOff(FeatureFlag $flag, Barangay $barangay): void
    {
        app(FeatureFlags::class)->override($flag, $barangay, false, null, null);
    }

    protected function turnOn(FeatureFlag $flag, Barangay $barangay): void
    {
        app(FeatureFlags::class)->override($flag, $barangay, true, null, null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function photographedResident(array $attributes = []): Resident
    {
        return Resident::factory()->create(['photo_path' => 'barangays/1/residents/1/photo.jpg', ...$attributes]);
    }
}
