<?php

namespace Database\Factories;

use App\Enums\PaymentType;
use App\Enums\VoucherStatus;
use App\Models\Budget;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'barangay_id' => fn (array $attributes) => Budget::acrossBarangays()->whereKey($attributes['budget_id'])->value('barangay_id'),
            'voucher_number' => fake()->unique()->numerify('DV-T######'),
            'payment_type' => PaymentType::Reimbursement,
            'status' => VoucherStatus::Prepared,
            'voucher_date' => now()->toDateString(),
            'payee_name' => 'Sample Payee',
            'particulars' => 'Sample disbursement',
            'prepared_at' => now(),
        ];
    }

    public function cashAdvance(): static
    {
        return $this->state(fn () => ['payment_type' => PaymentType::CashAdvance]);
    }

    public function inStatus(VoucherStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'approved_at' => $status->commitsFunds() ? now() : null,
            'released_at' => in_array($status, [VoucherStatus::Released, VoucherStatus::Closed], true) ? now() : null,
        ]);
    }
}
