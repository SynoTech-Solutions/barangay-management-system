<?php

namespace App\Http\Requests\Sms;

use App\Http\Requests\Residents\ResidentListRequest;
use Illuminate\Validation\Rule;

class ResidentSmsSelectionRequest extends ResidentListRequest
{
    public const string SCOPE_SELECTED = 'selected';

    public const string SCOPE_FILTERED = 'filtered';

    public const int MAX_SELECTED = 500;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'scope' => ['required', Rule::in([self::SCOPE_SELECTED, self::SCOPE_FILTERED])],
            'resident_ids' => ['required_if:scope,'.self::SCOPE_SELECTED, 'array', 'max:'.self::MAX_SELECTED],
            'resident_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function isFiltered(): bool
    {
        return $this->string('scope')->toString() === self::SCOPE_FILTERED;
    }

    /**
     * @return list<int>
     */
    public function residentIds(): array
    {
        return array_values(array_map('intval', (array) $this->input('resident_ids', [])));
    }

    /**
     * @return array<string, mixed>
     */
    public function selection(): array
    {
        return $this->isFiltered()
            ? ['scope' => self::SCOPE_FILTERED, ...$this->safe()->except(['scope', 'resident_ids', 'body', 'confirmed_count', 'sms_group_id', 'per_page'])]
            : ['scope' => self::SCOPE_SELECTED, 'resident_ids' => $this->residentIds()];
    }
}
