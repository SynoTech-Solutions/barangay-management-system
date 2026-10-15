<?php

namespace Database\Factories;

use App\Enums\PdfFieldType;
use App\Models\FormPdfMapping;
use App\Models\FormTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormPdfMapping>
 */
class FormPdfMappingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_template_version_id' => FormTemplateVersion::factory(),
            'barangay_id' => fn (array $attributes) => FormTemplateVersion::acrossBarangays()->whereKey($attributes['form_template_version_id'])->value('barangay_id'),
            'pdf_field' => 'field_'.fake()->unique()->numberBetween(1, 999999),
            'pdf_field_type' => PdfFieldType::Text,
            'token' => 'resident.full_name',
        ];
    }
}
