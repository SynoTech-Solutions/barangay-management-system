<?php

namespace App\Http\Requests\Sms;

use App\Rules\PhilippineMobile;
use App\Tenancy\CurrentBarangay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SmsGroupMemberRequest extends FormRequest
{
    private const int LABEL_MAX = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(CurrentBarangay $currentBarangay): array
    {
        return [
            'resident_id' => [
                'nullable',
                'required_without:mobile_number',
                'integer',
                Rule::exists('residents', 'id')->where('barangay_id', $currentBarangay->require()->id)->whereNull('deleted_at'),
            ],
            'mobile_number' => ['nullable', 'required_without:resident_id', 'prohibits:resident_id', 'string', new PhilippineMobile],
            'label' => ['nullable', 'string', 'max:'.self::LABEL_MAX],
        ];
    }

    public function residentId(): ?int
    {
        return $this->filled('resident_id') ? $this->integer('resident_id') : null;
    }

    public function mobileNumber(): ?string
    {
        return $this->filled('mobile_number') ? $this->string('mobile_number')->toString() : null;
    }

    public function label(): ?string
    {
        return $this->filled('label') ? $this->string('label')->toString() : null;
    }
}
