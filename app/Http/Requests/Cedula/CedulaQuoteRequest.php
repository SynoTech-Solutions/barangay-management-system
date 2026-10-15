<?php

namespace App\Http\Requests\Cedula;

use Illuminate\Foundation\Http\FormRequest;

class CedulaQuoteRequest extends FormRequest
{
    use ValidatesCommunityTaxInputs;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->incomeRules();
    }
}
