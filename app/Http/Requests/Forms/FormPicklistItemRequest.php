<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class FormPicklistItemRequest extends FormRequest
{
    public const int LABEL_MAX = 255;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:'.self::LABEL_MAX],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function label(): string
    {
        return $this->string('label')->squish()->toString();
    }

    public function active(): bool
    {
        return ! $this->has('is_active') || $this->boolean('is_active');
    }
}
