<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetVersion>
 */
class BudgetVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'barangay_id' => fn (array $attributes) => Budget::acrossBarangays()->whereKey($attributes['budget_id'])->value('barangay_id'),
            'number' => BudgetVersion::ORIGINAL,
        ];
    }
}
