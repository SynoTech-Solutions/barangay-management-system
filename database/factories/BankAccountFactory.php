<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Barangay;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'account_number' => fake()->unique()->numerify('000-####-####'),
            'account_name' => 'General Fund',
            'depository_bank' => 'Sample Bank',
            'branch_address' => null,
            'is_active' => true,
        ];
    }
}
