<?php

namespace App\Http\Requests\Cedula;

use App\Domain\Residents\NameNormalizer;
use App\Models\CedulaTemplate;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class CedulaTemplateRequest extends FormRequest
{
    use ValidatesCommunityTaxInputs;

    private const int NAME_MAX = 100;

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
        $template = $this->route('template');
        $template = $template instanceof CedulaTemplate ? $template : null;

        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, function (string $attribute, mixed $value, Closure $fail) use ($template): void {
                $taken = is_string($value) && CedulaTemplate::query()
                    ->whereRaw('lower(name) = lower(?)', [$value])
                    ->when($template !== null, fn ($query) => $query->whereKeyNot($template?->id))
                    ->exists();

                if ($taken) {
                    $fail('validation.unique')->translate();
                }
            }],
            ...$this->incomeRules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return ['name' => $this->string('name')->toString(), ...$this->inputs()];
    }
}
