<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class CheckReleaseRequest extends FormRequest
{
    use NormalizesText;

    private const int NAME_MAX = 150;

    protected function prepareForValidation(): void
    {
        $this->normalizeText(['received_by']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'received_by' => ['required', 'string', 'max:'.self::NAME_MAX],
            'confirmed' => ['accepted'],
        ];
    }

    public function receivedBy(): string
    {
        return $this->string('received_by')->toString();
    }
}
