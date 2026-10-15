<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Voucher;
use App\Models\VoucherItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoucherItem>
 */
class VoucherItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'voucher_id' => Voucher::factory(),
            'barangay_id' => fn (array $attributes) => Voucher::acrossBarangays()->whereKey($attributes['voucher_id'])->value('barangay_id'),
            'account_id' => fn (array $attributes) => Account::factory()->state(['barangay_id' => $attributes['barangay_id']]),
            'description' => 'Sample item',
            'amount' => '100.00',
            'position' => 1,
        ];
    }
}
