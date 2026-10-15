<?php

namespace App\Http\Requests\Cedula;

use App\Domain\Residents\NameNormalizer;
use App\Enums\IssuedDocumentStatus;
use App\Rules\ActiveResident;
use App\Tenancy\CurrentBarangay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CedulaRequest extends FormRequest
{
    use ValidatesCommunityTaxInputs;

    private const int FORM_NUMBER_MAX = 32;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('form_number'))) {
            $this->merge(['form_number' => NameNormalizer::normalize($this->input('form_number'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(CurrentBarangay $currentBarangay): array
    {
        return [
            'resident_id' => ['required', new ActiveResident],
            'form_number' => [
                'nullable', 'string', 'max:'.self::FORM_NUMBER_MAX,
                Rule::unique('cedulas', 'form_number')
                    ->where('barangay_id', $currentBarangay->require()->id)
                    ->where(fn ($query) => $query->where('status', '<>', IssuedDocumentStatus::Cancelled->value)),
            ],
            ...$this->incomeRules(),
        ];
    }

    public function residentId(): int
    {
        return $this->integer('resident_id');
    }

    public function formNumber(): ?string
    {
        return $this->filled('form_number') ? $this->string('form_number')->toString() : null;
    }
}
