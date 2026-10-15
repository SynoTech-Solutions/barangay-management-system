<?php

namespace App\Http\Requests\Sms;

use App\Rules\SmsBody;
use Illuminate\Foundation\Http\FormRequest;

class SmsBodyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.SmsComposeRequest::BODY_INPUT_MAX, new SmsBody],
        ];
    }

    public function body(): string
    {
        return $this->string('body')->toString();
    }
}
