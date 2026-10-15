<?php

namespace Database\Factories;

use App\Domain\Reports\ReportDefinition;
use App\Enums\ReportDataset;
use App\Models\Barangay;
use App\Models\SavedReport;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedReport>
 */
class SavedReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'name' => 'Report '.fake()->unique()->numberBetween(1, 999999),
            'dataset' => ReportDataset::Residents,
            'definition' => (new ReportDefinition(ReportDataset::Residents, ['resident_number', 'full_name']))->toArray(),
            'is_active' => true,
        ];
    }

    public function definedAs(ReportDefinition $definition): static
    {
        return $this->state(fn () => ['dataset' => $definition->dataset, 'definition' => $definition->toArray()]);
    }
}
