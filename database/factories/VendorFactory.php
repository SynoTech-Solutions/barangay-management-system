<?php

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\Vendor;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'name' => 'Sample Supplies '.fake()->unique()->numberBetween(1, 999999),
            'address' => 'Sample Street, Sample Town',
            'contact_number' => '0917 000 0000',
            'email' => null,
            'contact_person' => null,
            'position' => null,
            'tin' => null,
            'is_active' => true,
        ];
    }
}
