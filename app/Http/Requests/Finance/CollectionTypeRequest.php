<?php

namespace App\Http\Requests\Finance;

use App\Models\CollectionType;
use Illuminate\Foundation\Http\FormRequest;

class CollectionTypeRequest extends FormRequest
{
    use NormalizesText;

    private const array TEXT_FIELDS = ['name'];

    private const int NAME_MAX = 100;

    protected function prepareForValidation(): void
    {
        $this->normalizeText(self::TEXT_FIELDS);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->route('collectionType');

        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, $this->uniqueIgnoringCase(CollectionType::class, 'name', $type instanceof CollectionType ? $type->id : null)],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }
}
