<?php

namespace App\Http\Requests\Certificates;

use App\Enums\LookupType;
use App\Models\IndigencyCertificate;
use App\Rules\ActiveResident;
use App\Rules\LookupOption;
use Illuminate\Foundation\Http\FormRequest;

class IndigencyCertificateRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $certificate = $this->route('certificate');
        $certificate = $certificate instanceof IndigencyCertificate ? $certificate : null;

        return [
            'resident_id' => $certificate === null ? ['required', new ActiveResident] : ['prohibited'],
            'purpose_id' => ['required', new LookupOption(LookupType::CertificatePurpose, $certificate?->purpose_id)],
        ];
    }

    public function residentId(): int
    {
        return $this->integer('resident_id');
    }

    public function purposeId(): int
    {
        return $this->integer('purpose_id');
    }
}
