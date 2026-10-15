<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\BudgetLine;
use App\Models\BudgetVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetLine>
 */
class BudgetLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_version_id' => BudgetVersion::factory(),
            'barangay_id' => fn (array $attributes) => BudgetVersion::acrossBarangays()->whereKey($attributes['budget_version_id'])->value('barangay_id'),
            'account_id' => Account::factory(),
            'amount' => '1000.00',
        ];
    }
}
