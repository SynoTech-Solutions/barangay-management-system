<?php

namespace App\Http\Requests\Sms;

use App\Rules\PhilippineMobile;
use App\Rules\SmsBody;
use App\Tenancy\CurrentBarangay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SmsComposeRequest extends FormRequest
{
    public const int MAX_PICKED_RESIDENTS = 200;

    public const int MAX_TYPED_NUMBERS = 200;

    public const int BODY_INPUT_MAX = 2000;

    private const string NUMBER_SEPARATORS = '/[,;\n]+/';

    protected function prepareForValidation(): void
    {
        $numbers = $this->input('numbers');
        if (is_string($numbers)) {
            $this->merge(['numbers' => array_values(array_filter(
                array_map(fn (string $number): string => trim($number), preg_split(self::NUMBER_SEPARATORS, $numbers) ?: []),
                fn (string $number): bool => $number !== '',
            ))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(CurrentBarangay $currentBarangay): array
    {
        return [
            'resident_ids' => ['nullable', 'array', 'max:'.self::MAX_PICKED_RESIDENTS],
            'resident_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('residents', 'id')->where('barangay_id', $currentBarangay->require()->id)->whereNull('deleted_at'),
            ],
            'numbers' => ['nullable', 'array', 'max:'.self::MAX_TYPED_NUMBERS],
            'numbers.*' => ['string', new PhilippineMobile],
            'body' => ['required', 'string', 'max:'.self::BODY_INPUT_MAX, new SmsBody],
        ];
    }

    /**
     * @return list<int>
     */
    public function residentIds(): array
    {
        return array_values(array_map('intval', (array) $this->input('resident_ids', [])));
    }

    /**
     * @return list<string>
     */
    public function numbers(): array
    {
        return array_values(array_map('strval', (array) $this->input('numbers', [])));
    }

    public function body(): string
    {
        return $this->string('body')->toString();
    }
}
