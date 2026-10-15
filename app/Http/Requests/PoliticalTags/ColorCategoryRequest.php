<?php

namespace App\Http\Requests\PoliticalTags;

use App\Models\ColorCategory;
use Illuminate\Foundation\Http\FormRequest;

class ColorCategoryRequest extends FormRequest
{
    private const int NAME_MAX = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'color_hex' => ['required', 'string', 'regex:'.ColorCategory::COLOR_PATTERN],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }

    public function colorHex(): string
    {
        return $this->string('color_hex')->toString();
    }
}
