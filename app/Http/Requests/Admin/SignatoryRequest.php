<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SignatoryRequest extends FormRequest
{
    private const int TEXT_MAX = 255;

    private const int SIGNATURE_MAX_KILOBYTES = 1024;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::TEXT_MAX],
            'title' => ['nullable', 'string', 'max:'.self::TEXT_MAX],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:'.self::SIGNATURE_MAX_KILOBYTES],
            'remove_signature' => ['boolean'],
        ];
    }
}
