<?php

namespace App\Http\Requests\Sms;

use App\Domain\Residents\NameNormalizer;
use App\Models\SmsTemplate;
use App\Rules\SmsBody;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SmsTemplateRequest extends FormRequest
{
    public const int TITLE_MAX = 50;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => NameNormalizer::normalize($this->input('title'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $template = $this->route('template');
        $template = $template instanceof SmsTemplate ? $template : null;

        return [
            'title' => ['required', 'string', 'max:'.self::TITLE_MAX, function (string $attribute, mixed $value, Closure $fail) use ($template): void {
                $taken = is_string($value) && SmsTemplate::query()
                    ->whereRaw('lower(title) = lower(?)', [$value])
                    ->when($template !== null, fn ($query) => $query->whereKeyNot($template?->id))
                    ->exists();

                if ($taken) {
                    $fail('sms.validation.template_title_taken')->translate();
                }
            }],
            'body' => ['required', 'string', 'max:'.SmsComposeRequest::BODY_INPUT_MAX, new SmsBody],
        ];
    }

    public function templateTitle(): string
    {
        return $this->string('title')->toString();
    }

    public function body(): string
    {
        return $this->string('body')->toString();
    }
}
