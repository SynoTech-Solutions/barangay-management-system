<?php

namespace App\Http\Requests\PoliticalTags;

use App\Http\Requests\Residents\BulkPurokRequest;
use App\Tenancy\CurrentBarangay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkColorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(CurrentBarangay $currentBarangay): array
    {
        $barangayId = $currentBarangay->require()->id;

        return [
            'resident_ids' => ['required', 'array', 'min:1', 'max:'.BulkPurokRequest::MAX_RESIDENTS],
            'resident_ids.*' => ['integer', 'distinct', Rule::exists('residents', 'id')->where('barangay_id', $barangayId)->whereNull('deleted_at')],
            'color_category_id' => ['nullable', 'integer', Rule::exists('color_categories', 'id')->where('barangay_id', $barangayId)->where('is_active', true)],
        ];
    }

    /**
     * @return list<int>
     */
    public function residentIds(): array
    {
        return array_values(array_map('intval', (array) $this->input('resident_ids', [])));
    }

    public function categoryId(): ?int
    {
        return $this->filled('color_category_id') ? $this->integer('color_category_id') : null;
    }
}
