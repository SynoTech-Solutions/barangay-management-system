<?php

namespace App\Http\Requests\Residents;

use App\Rules\PurokOption;
use App\Tenancy\CurrentBarangay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkPurokRequest extends FormRequest
{
    public const int MAX_RESIDENTS = 500;

    /**
     * @return array<string, mixed>
     */
    public function rules(CurrentBarangay $currentBarangay): array
    {
        return [
            'resident_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_RESIDENTS],
            'resident_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('residents', 'id')->where('barangay_id', $currentBarangay->require()->id)->whereNull('deleted_at'),
            ],
            'purok_id' => ['required', new PurokOption],
        ];
    }

    /**
     * @return list<int>
     */
    public function residentIds(): array
    {
        return array_values(array_map('intval', (array) $this->input('resident_ids', [])));
    }

    public function purokId(): int
    {
        return $this->integer('purok_id');
    }
}
