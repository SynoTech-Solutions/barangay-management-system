<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class FormPicklistRequest extends FormRequest
{
    public const int NAME_MAX = 120;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->squish()->toString();
    }

    public function active(): bool
    {
        return ! $this->has('is_active') || $this->boolean('is_active');
    }
}
