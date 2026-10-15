<?php

namespace App\Http\Requests\Finance;

use App\Domain\Residents\NameNormalizer;
use App\Enums\AccountGroupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountUpdateRequest extends FormRequest
{
    private const array TEXT_FIELDS = ['name', 'remarks'];

    private const int NAME_MAX = 200;

    private const int REMARKS_MAX = 255;

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (self::TEXT_FIELDS as $field) {
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
            'name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'remarks' => ['nullable', 'string', 'max:'.self::REMARKS_MAX],
            'group_type' => ['nullable', Rule::enum(AccountGroupType::class)],
        ];
    }

    /**
     * @return array{name: string, remarks: string|null, group_type: AccountGroupType|null}
     */
    public function details(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'remarks' => $this->filled('remarks') ? $this->string('remarks')->toString() : null,
            'group_type' => AccountGroupType::tryFrom($this->string('group_type')->toString()),
        ];
    }
}
