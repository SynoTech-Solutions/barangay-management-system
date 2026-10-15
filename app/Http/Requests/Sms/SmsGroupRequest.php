<?php

namespace App\Http\Requests\Sms;

use App\Domain\Residents\NameNormalizer;
use App\Models\SmsGroup;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SmsGroupRequest extends FormRequest
{
    public const int NAME_MAX = 50;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => NameNormalizer::normalize($this->input('name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $group = $this->route('group');
        $group = $group instanceof SmsGroup ? $group : null;

        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, function (string $attribute, mixed $value, Closure $fail) use ($group): void {
                $taken = is_string($value) && SmsGroup::query()
                    ->whereRaw('lower(name) = lower(?)', [$value])
                    ->when($group !== null, fn ($query) => $query->whereKeyNot($group?->id))
                    ->exists();

                if ($taken) {
                    $fail('sms.validation.group_name_taken')->translate();
                }
            }],
        ];
    }

    public function groupName(): string
    {
        return $this->string('name')->toString();
    }
}
