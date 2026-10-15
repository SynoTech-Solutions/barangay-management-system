<?php

namespace App\Http\Requests\Admin;

use App\Enums\ClearanceValidityMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BarangayProfileRequest extends FormRequest
{
    private const int TEXT_MAX = 255;

    private const int PHONE_MAX = 64;

    private const int ORDINANCE_MAX = 128;

    private const int VALIDITY_MONTHS_MIN = 1;

    private const int VALIDITY_MONTHS_MAX = 36;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'telephone' => ['nullable', 'string', 'max:'.self::PHONE_MAX],
            'email' => ['nullable', 'email', 'max:'.self::TEXT_MAX],
            'id_card_ordinance_no' => ['nullable', 'string', 'max:'.self::ORDINANCE_MAX],
            'clearance_validity_mode' => ['required', Rule::enum(ClearanceValidityMode::class)],
            'clearance_validity_months' => [
                Rule::requiredIf(fn (): bool => $this->input('clearance_validity_mode') === ClearanceValidityMode::Months->value),
                'nullable', 'integer', 'between:'.self::VALIDITY_MONTHS_MIN.','.self::VALIDITY_MONTHS_MAX,
            ],
        ];
    }

    /**
     * @return array{telephone: string|null, email: string|null, id_card_ordinance_no: string|null, clearance_validity_mode: ClearanceValidityMode, clearance_validity_months: int|null}
     */
    public function attributes(): array
    {
        return [
            'telephone' => $this->filled('telephone') ? $this->string('telephone')->trim()->toString() : null,
            'email' => $this->filled('email') ? $this->string('email')->trim()->toString() : null,
            'id_card_ordinance_no' => $this->filled('id_card_ordinance_no') ? $this->string('id_card_ordinance_no')->trim()->toString() : null,
            'clearance_validity_mode' => ClearanceValidityMode::from($this->string('clearance_validity_mode')->toString()),
            'clearance_validity_months' => $this->filled('clearance_validity_months') ? $this->integer('clearance_validity_months') : null,
        ];
    }
}
