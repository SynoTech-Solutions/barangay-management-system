<?php

namespace Database\Factories;

use App\Models\FormTemplate;
use App\Models\FormTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormTemplateVersion>
 */
class FormTemplateVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_template_id' => FormTemplate::factory(),
            'barangay_id' => fn (array $attributes) => FormTemplate::acrossBarangays()->whereKey($attributes['form_template_id'])->value('barangay_id'),
            'version' => fn (array $attributes) => (int) FormTemplateVersion::acrossBarangays()->where('form_template_id', $attributes['form_template_id'])->max('version') + 1,
            'body' => '<p>{{resident.full_name}}</p>',
            'created_at' => now(),
        ];
    }
}
