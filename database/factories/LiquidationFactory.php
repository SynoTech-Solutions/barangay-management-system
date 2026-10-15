<?php

namespace Database\Factories;

use App\Enums\LiquidationStatus;
use App\Enums\VoucherStatus;
use App\Models\Liquidation;
use App\Models\Voucher;
use Database\Factories\Concerns\RecordsOfficialReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Liquidation>
 */
class LiquidationFactory extends Factory
{
    use RecordsOfficialReceipt;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'voucher_id' => Voucher::factory()->cashAdvance()->inStatus(VoucherStatus::Closed),
            'barangay_id' => fn (array $attributes) => Voucher::acrossBarangays()->whereKey($attributes['voucher_id'])->value('barangay_id'),
            'liquidation_number' => fake()->unique()->numerify('LR-T######'),
            'liquidated_on' => now()->toDateString(),
            'status' => LiquidationStatus::ForClearing,
            'recorded_at' => now(),
        ];
    }
}
