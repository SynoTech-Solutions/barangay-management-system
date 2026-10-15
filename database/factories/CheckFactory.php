<?php

namespace Database\Factories;

use App\Enums\CheckStatus;
use App\Models\BankAccount;
use App\Models\Check;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Check>
 */
class CheckFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bank_account_id' => BankAccount::factory(),
            'barangay_id' => fn (array $attributes) => BankAccount::acrossBarangays()->whereKey($attributes['bank_account_id'])->value('barangay_id'),
            'voucher_id' => null,
            'payee_name' => 'Sample Payee',
            'check_number' => fake()->unique()->numerify('########'),
            'check_date' => now()->toDateString(),
            'amount' => '100.00',
            'status' => CheckStatus::ForRelease,
            'issued_at' => now(),
        ];
    }

    public function released(): static
    {
        return $this->state(fn () => ['status' => CheckStatus::Released, 'released_at' => now(), 'received_by_name' => 'Sample Receiver']);
    }
}
