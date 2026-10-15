<?php

namespace Database\Factories;

use App\Enums\BudgetStatus;
use App\Models\Barangay;
use App\Models\Budget;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2001, 2099);

        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'fiscal_year' => $year,
            'title' => "Budget {$year}",
            'status' => BudgetStatus::Draft,
        ];
    }
}
