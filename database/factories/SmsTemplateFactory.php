<?php

namespace Database\Factories;

use App\Models\Barangay;
use App\Models\SmsTemplate;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsTemplate>
 */
class SmsTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'title' => 'Template '.fake()->unique()->numberBetween(1, 99999),
            'body' => 'Barangay assembly this Saturday at 8 AM.',
        ];
    }
}
