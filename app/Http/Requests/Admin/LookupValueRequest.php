<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LookupValueRequest extends FormRequest
{
    private const int LABEL_MAX = 255;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:'.self::LABEL_MAX],
        ];
    }

    public function label(): string
    {
        return $this->string('label')->toString();
    }
}
