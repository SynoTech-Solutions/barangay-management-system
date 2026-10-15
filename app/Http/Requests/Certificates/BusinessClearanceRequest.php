<?php

namespace App\Http\Requests\Certificates;

use App\Http\Requests\Documents\ValidatesDocumentPayment;
use App\Models\BusinessClearance;
use App\Tenancy\CurrentBarangay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessClearanceRequest extends FormRequest
{
    use ValidatesDocumentPayment;

    protected function prepareForValidation(): void
    {
        $this->normalizePaymentText();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(CurrentBarangay $currentBarangay): array
    {
        $clearance = $this->route('businessClearance');
        $clearance = $clearance instanceof BusinessClearance ? $clearance : null;

        return [
            'business_id' => $clearance === null
                ? ['required', 'integer', Rule::exists('businesses', 'id')->where('barangay_id', $currentBarangay->require()->id)]
                : ['prohibited'],
            ...$this->paymentRules($clearance, withDocStamp: true),
        ];
    }

    public function businessId(): int
    {
        return $this->integer('business_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return [
            ...$this->paymentDetails(),
            'doc_stamp' => $this->optionalText('doc_stamp') ?? '0',
        ];
    }
}
