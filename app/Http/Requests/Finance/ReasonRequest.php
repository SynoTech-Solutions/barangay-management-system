<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ReasonRequest extends FormRequest
{
    private const int REASON_MAX = 255;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:'.self::REASON_MAX]];
    }

    public function reason(): string
    {
        return $this->string('reason')->trim()->toString();
    }
}
