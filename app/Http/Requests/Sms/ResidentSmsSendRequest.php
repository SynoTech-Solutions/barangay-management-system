<?php

namespace App\Http\Requests\Sms;

use App\Rules\SmsBody;

class ResidentSmsSendRequest extends ResidentSmsSelectionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'body' => ['required', 'string', 'max:'.SmsComposeRequest::BODY_INPUT_MAX, new SmsBody],
            'confirmed_count' => ['required', 'integer', 'min:1'],
        ];
    }

    public function body(): string
    {
        return $this->string('body')->toString();
    }

    public function confirmedCount(): int
    {
        return $this->integer('confirmed_count');
    }
}
