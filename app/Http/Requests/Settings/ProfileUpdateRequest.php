<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    private const int TEXT_MAX = 255;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::TEXT_MAX],
            'email' => ['nullable', 'email', 'max:'.self::TEXT_MAX],
        ];
    }

    /**
     * @return array{name: string, email: string|null}
     */
    public function profile(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'email' => $this->filled('email') ? $this->string('email')->trim()->lower()->toString() : null,
        ];
    }
}
