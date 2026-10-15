<?php

namespace Database\Factories;

use App\Models\FormPicklist;
use App\Models\FormPicklistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormPicklistItem>
 */
class FormPicklistItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_picklist_id' => FormPicklist::factory(),
            'barangay_id' => fn (array $attributes) => FormPicklist::acrossBarangays()->whereKey($attributes['form_picklist_id'])->value('barangay_id'),
            'label' => 'Item '.fake()->unique()->numberBetween(1, 999999),
            'is_active' => true,
        ];
    }
}
