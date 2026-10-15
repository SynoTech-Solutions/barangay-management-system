<?php

namespace Database\Factories;

use App\Domain\Forms\FormSnapshot;
use App\Enums\IssuedDocumentStatus;
use App\Models\FormTemplate;
use App\Models\FormTransaction;
use App\Models\Resident;
use Database\Factories\Concerns\RecordsOfficialReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormTransaction>
 */
class FormTransactionFactory extends Factory
{
    use RecordsOfficialReceipt;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resident_id' => Resident::factory(),
            'barangay_id' => fn (array $attributes) => Resident::acrossBarangays()->whereKey($attributes['resident_id'])->value('barangay_id'),
            'form_template_id' => fn () => FormTemplate::factory()->withVersion(),
            'form_template_version_id' => fn (array $attributes) => FormTemplate::acrossBarangays()->whereKey($attributes['form_template_id'])->value('current_version_id'),
            'transaction_number' => fake()->unique()->numerify('FX-T######'),
            'status' => IssuedDocumentStatus::Issued,
            'amount' => '0.00',
            'snapshot' => (new FormSnapshot([], []))->toArray(),
            'issued_at' => now(),
        ];
    }

    public function released(): static
    {
        return $this->state(fn () => ['status' => IssuedDocumentStatus::Released, 'released_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => IssuedDocumentStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => 'Wrong entry']);
    }
}
