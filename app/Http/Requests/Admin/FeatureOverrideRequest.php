<?php

namespace App\Http\Requests\Admin;

use App\Enums\FeatureFlag;
use App\Enums\FeatureOverrideState;
use App\Enums\FeatureScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeatureOverrideRequest extends FormRequest
{
    private const int REASON_MAX = 500;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'flag' => ['required', Rule::enum(FeatureFlag::class)],
            'state' => ['required', Rule::enum(FeatureOverrideState::class)],
            'scope' => ['required', Rule::enum(FeatureScope::class)],
            'reason' => ['nullable', 'string', 'max:'.self::REASON_MAX],
        ];
    }

    public function flag(): FeatureFlag
    {
        return FeatureFlag::from($this->string('flag')->toString());
    }

    public function state(): FeatureOverrideState
    {
        return FeatureOverrideState::from($this->string('state')->toString());
    }

    public function scope(): FeatureScope
    {
        return FeatureScope::from($this->string('scope')->toString());
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
