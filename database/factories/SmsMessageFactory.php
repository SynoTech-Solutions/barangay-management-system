<?php

namespace Database\Factories;

use App\Enums\SmsEncoding;
use App\Enums\SmsStatus;
use App\Models\Barangay;
use App\Models\SmsMessage;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsMessage>
 */
class SmsMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'recipient' => '+63917000'.fake()->unique()->numerify('####'),
            'body' => 'Barangay assembly this Saturday at 8 AM.',
            'encoding' => SmsEncoding::Gsm7,
            'segments' => 1,
            'status' => SmsStatus::Queued,
            'attempts' => 0,
            'queued_at' => now(),
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => SmsStatus::Sent, 'attempts' => 1, 'sent_at' => now(), 'provider_message_id' => 'test-'.fake()->uuid()]);
    }

    public function failed(string $code = 'http_400'): static
    {
        return $this->state(fn () => ['status' => SmsStatus::Failed, 'attempts' => 1, 'failed_at' => now(), 'last_error_code' => $code]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => SmsStatus::Cancelled, 'cancelled_at' => now()]);
    }

    public function sending(): static
    {
        return $this->state(fn () => ['status' => SmsStatus::Sending, 'attempts' => 1, 'sending_started_at' => now()]);
    }
}
