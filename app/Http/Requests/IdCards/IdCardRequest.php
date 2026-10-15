<?php

namespace App\Http\Requests\IdCards;

use App\Http\Requests\Documents\ValidatesDocumentPayment;
use App\Models\IdCard;
use App\Rules\ActiveResident;
use Illuminate\Foundation\Http\FormRequest;

class IdCardRequest extends FormRequest
{
    use ValidatesDocumentPayment;

    protected function prepareForValidation(): void
    {
        $this->normalizePaymentText();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $card = $this->route('idCard');
        $card = $card instanceof IdCard ? $card : null;

        return [
            'resident_id' => $card === null ? ['required', new ActiveResident] : ['prohibited'],
            ...$this->paymentRules($card, withDocStamp: false),
        ];
    }

    public function residentId(): int
    {
        return $this->integer('resident_id');
    }

    /**
     * @return array{ctc_number: string|null, ctc_place: string|null, ctc_date: string|null, or_number: string|null, amount: string}
     */
    public function details(): array
    {
        return $this->paymentDetails();
    }
}
