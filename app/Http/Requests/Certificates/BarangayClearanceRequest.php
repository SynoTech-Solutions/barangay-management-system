<?php

namespace App\Http\Requests\Certificates;

use App\Enums\LookupType;
use App\Http\Requests\Documents\ValidatesDocumentPayment;
use App\Models\BarangayClearance;
use App\Rules\ActiveResident;
use App\Rules\LookupOption;
use Illuminate\Foundation\Http\FormRequest;

class BarangayClearanceRequest extends FormRequest
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
        $clearance = $this->clearance();

        return [
            'resident_id' => $clearance === null ? ['required', new ActiveResident] : ['prohibited'],
            'clearance_type_id' => ['required', new LookupOption(LookupType::ClearanceType, $clearance?->clearance_type_id)],
            'purpose_id' => ['required', new LookupOption(LookupType::ClearancePurpose, $clearance?->purpose_id)],
            ...$this->paymentRules($clearance, withDocStamp: true),
        ];
    }

    public function residentId(): int
    {
        return $this->integer('resident_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return [
            'clearance_type_id' => $this->integer('clearance_type_id'),
            'purpose_id' => $this->integer('purpose_id'),
            ...$this->paymentDetails(),
            'doc_stamp' => $this->optionalText('doc_stamp') ?? '0',
        ];
    }

    private function clearance(): ?BarangayClearance
    {
        $clearance = $this->route('clearance');

        return $clearance instanceof BarangayClearance ? $clearance : null;
    }
}
