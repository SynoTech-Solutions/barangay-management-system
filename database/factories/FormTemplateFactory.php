<?php

namespace Database\Factories;

use App\Enums\FormTemplateKind;
use App\Enums\LookupType;
use App\Models\Barangay;
use App\Models\FormTemplate;
use App\Models\FormTemplateVersion;
use App\Models\LookupValue;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormTemplate>
 */
class FormTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barangay_id' => fn () => app(CurrentBarangay::class)->id() ?? Barangay::factory(),
            'template_number' => fake()->unique()->numerify('FT-T######'),
            'type_id' => LookupValue::factory()->ofType(LookupType::FormTemplateType),
            'name' => 'Template '.fake()->unique()->numberBetween(1, 999999),
            'kind' => FormTemplateKind::RichText,
            'is_active' => true,
        ];
    }

    public function withVersion(string $body = '<p>Certified for {{resident.full_name}}.</p>'): static
    {
        return $this->afterCreating(function (FormTemplate $template) use ($body): void {
            $version = FormTemplateVersion::factory()->create([
                'barangay_id' => $template->barangay_id,
                'form_template_id' => $template->id,
                'body' => $template->kind === FormTemplateKind::RichText ? $body : null,
            ]);
            $template->current_version_id = $version->id;
            $template->save();
        });
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
