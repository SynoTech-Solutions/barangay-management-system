<?php

namespace Database\Factories;

use App\Enums\SmsSource;
use App\Models\Barangay;
use App\Models\SmsBatch;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsBatch>
 */
class SmsBatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'source' => SmsSource::Compose,
            'sms_group_id' => null,
            'recipient_count' => 1,
            'skipped_count' => 0,
            'segments' => 1,
        ];
    }
}
