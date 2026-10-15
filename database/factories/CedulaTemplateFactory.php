<?php

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\CedulaTemplate;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CedulaTemplate>
 */
class CedulaTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'name' => 'Preset '.fake()->unique()->numberBetween(1, 999999),
            'is_exempt' => false,
            'business_gross_receipts' => '0.00',
            'salaries' => '120000.00',
            'real_property_income' => '0.00',
            'is_active' => true,
        ];
    }
}
