<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;

class FormPreviewRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.FormTemplateRequest::BODY_MAX],
        ];
    }

    public function body(): string
    {
        return $this->string('body')->toString();
    }
}
