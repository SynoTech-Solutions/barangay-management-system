<?php

namespace App\Http\Requests\Residents;

use App\Domain\Residents\NameNormalizer;
use App\Http\Requests\Residents\Concerns\WarnsAboutDuplicates;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class QuickAddResidentRequest extends FormRequest
{
    use WarnsAboutDuplicates;

    private const array NAME_FIELDS = ['last_name', 'first_name', 'middle_name', 'name_extension'];

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (self::NAME_FIELDS as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = NameNormalizer::normalize($this->input($field));
            }
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'last_name' => ['required', 'string', 'max:'.ResidentRequest::NAME_MAX],
            'first_name' => ['required', 'string', 'max:'.ResidentRequest::NAME_MAX],
            'middle_name' => ['nullable', 'string', 'max:'.ResidentRequest::NAME_MAX],
            'name_extension' => ['nullable', 'string', 'max:'.ResidentRequest::EXTENSION_MAX],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
            'confirm_duplicate' => ['boolean'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->warnAboutDuplicate($validator)];
    }

    /**
     * @return array{last_name: string, first_name: string, middle_name: string|null, name_extension: string|null, birthdate: string|null}
     */
    public function profile(): array
    {
        return [
            'last_name' => $this->string('last_name')->toString(),
            'first_name' => $this->string('first_name')->toString(),
            'middle_name' => $this->filled('middle_name') ? $this->string('middle_name')->toString() : null,
            'name_extension' => $this->filled('name_extension') ? $this->string('name_extension')->toString() : null,
            'birthdate' => $this->filled('birthdate') ? $this->string('birthdate')->toString() : null,
        ];
    }
}
