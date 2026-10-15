<?php

namespace App\Http\Requests\Sms;

use App\Tenancy\CurrentBarangay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SmsMessageBatchRequest extends FormRequest
{
    public const int MAX_MESSAGES = 200;

    /**
     * @return array<string, mixed>
     */
    public function rules(CurrentBarangay $currentBarangay): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_MESSAGES],
            'ids.*' => ['integer', 'distinct', Rule::exists('sms_messages', 'id')->where('barangay_id', $currentBarangay->require()->id)],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map('intval', (array) $this->input('ids', [])));
    }
}
