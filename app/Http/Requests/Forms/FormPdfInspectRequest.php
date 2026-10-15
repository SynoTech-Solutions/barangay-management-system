<?php

namespace App\Http\Requests\Forms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class FormPdfInspectRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:'.FormTemplateRequest::PDF_MAX_KILOBYTES],
        ];
    }

    public function pdf(): UploadedFile
    {
        $file = $this->file('pdf');
        abort_unless($file instanceof UploadedFile, Response::HTTP_UNPROCESSABLE_ENTITY);

        return $file;
    }
}
