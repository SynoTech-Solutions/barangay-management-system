<?php

namespace App\Http\Requests\Cedula;

use App\Tenancy\CurrentBarangay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CedulaReferenceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(CurrentBarangay $currentBarangay): array
    {
        return [
            'resident_id' => ['required', 'integer', Rule::exists('residents', 'id')->where('barangay_id', $currentBarangay->require()->id)],
        ];
    }

    public function residentId(): int
    {
        return $this->integer('resident_id');
    }
}
