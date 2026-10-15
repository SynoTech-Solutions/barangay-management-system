<?php

namespace App\Http\Requests\Blotter;

use Illuminate\Foundation\Http\FormRequest;

class BlotterResolveRequest extends FormRequest
{
    private const int NOTES_MAX = 2_000;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['notes' => ['nullable', 'string', 'max:'.self::NOTES_MAX]];
    }

    public function notes(): ?string
    {
        return $this->filled('notes') ? trim($this->string('notes')->toString()) : null;
    }
}
