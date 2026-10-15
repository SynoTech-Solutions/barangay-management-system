<?php

namespace Database\Factories;

use App\Enums\FormInputType;
use App\Models\FormTemplateField;
use App\Models\FormTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormTemplateField>
 */
class FormTemplateFieldFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_template_version_id' => FormTemplateVersion::factory(),
            'barangay_id' => fn (array $attributes) => FormTemplateVersion::acrossBarangays()->whereKey($attributes['form_template_version_id'])->value('barangay_id'),
            'key' => 'field_'.fake()->unique()->numberBetween(1, 999999),
            'label' => 'Field',
            'input_type' => FormInputType::Text,
            'is_required' => false,
            'position' => 1,
        ];
    }
}
