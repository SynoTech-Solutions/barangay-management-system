<?php

namespace Database\Factories;

use App\Models\Liquidation;
use App\Models\LiquidationItem;
use App\Models\VoucherItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiquidationItem>
 */
class LiquidationItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'liquidation_id' => Liquidation::factory(),
            'barangay_id' => fn (array $attributes) => Liquidation::acrossBarangays()->whereKey($attributes['liquidation_id'])->value('barangay_id'),
            'voucher_item_id' => fn (array $attributes) => VoucherItem::factory()->state([
                'voucher_id' => Liquidation::acrossBarangays()->whereKey($attributes['liquidation_id'])->value('voucher_id'),
            ]),
            'actual_amount' => '100.00',
        ];
    }
}
