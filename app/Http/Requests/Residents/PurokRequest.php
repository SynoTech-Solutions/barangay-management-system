<?php

namespace App\Http\Requests\Residents;

use Illuminate\Foundation\Http\FormRequest;

class PurokRequest extends FormRequest
{
    private const int NAME_MAX = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }
}
