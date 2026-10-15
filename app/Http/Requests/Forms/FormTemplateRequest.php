<?php

namespace App\Http\Requests\Forms;

use App\Domain\Forms\FieldDraft;
use App\Domain\Forms\FieldKey;
use App\Domain\Forms\Pdf\AcroFormReader;
use App\Domain\Forms\PdfMappingDraft;
use App\Domain\Forms\TemplateContent;
use App\Enums\FormInputType;
use App\Enums\FormTemplateKind;
use App\Enums\LookupType;
use App\Models\FormTemplate;
use App\Queries\FormTemplateQuery;
use App\Rules\LookupOption;
use App\Tenancy\CurrentBarangay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class FormTemplateRequest extends FormRequest
{
    public const int NAME_MAX = 150;

    public const int BODY_MAX = 200_000;

    public const int PDF_MAX_KILOBYTES = 5120;

    public const int FIELDS_MAX = 60;

    public const int LABEL_MAX = 120;

    public const int TOKEN_MAX = 80;

    public const int MAPPED_VALUE_MAX = 64;

    public const int PDF_FIELD_MAX = 255;

    private const string PDF_MIMES = 'pdf';

    /**
     * @return array<string, mixed>
     */
    public function rules(CurrentBarangay $currentBarangay, FormTemplateQuery $templates): array
    {
        $template = $this->template();
        $kind = $template->kind ?? FormTemplateKind::tryFrom($this->string('kind')->toString());
        $inputTypes = array_map(fn (FormInputType $type): string => $type->value, $templates->enabledInputTypes());
        $kinds = array_map(fn (FormTemplateKind $kind): string => $kind->value, $templates->enabledKinds());

        return [
            'type_id' => ['required', new LookupOption(LookupType::FormTemplateType, $template?->type_id)],
            'name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'kind' => $template === null ? ['required', Rule::in($kinds)] : ['prohibited'],
            'body' => $kind === FormTemplateKind::RichText ? ['required', 'string', 'max:'.self::BODY_MAX] : ['prohibited'],
            'pdf' => $kind === FormTemplateKind::Pdf
                ? [$template === null ? 'required' : 'nullable', 'file', 'mimes:'.self::PDF_MIMES, 'max:'.self::PDF_MAX_KILOBYTES]
                : ['prohibited'],
            'fields' => ['nullable', 'array', 'max:'.self::FIELDS_MAX],
            'fields.*.key' => ['required', 'string', 'distinct', 'regex:'.FieldKey::PATTERN],
            'fields.*.label' => ['required', 'string', 'max:'.self::LABEL_MAX],
            'fields.*.input_type' => ['required', Rule::in($inputTypes)],
            'fields.*.is_required' => ['boolean'],
            'fields.*.picklist_id' => [
                'nullable',
                'required_if:fields.*.input_type,'.FormInputType::Table->value,
                'integer',
                Rule::exists('form_picklists', 'id')->where('barangay_id', $currentBarangay->require()->id),
            ],
            'mappings' => $kind === FormTemplateKind::Pdf ? ['array', 'max:'.AcroFormReader::MAX_FIELDS] : ['prohibited'],
            'mappings.*.pdf_field' => ['required', 'string', 'distinct', 'max:'.self::PDF_FIELD_MAX],
            'mappings.*.token' => ['nullable', 'string', 'max:'.self::TOKEN_MAX],
            'mappings.*.checked_value' => ['nullable', 'string', 'max:'.self::MAPPED_VALUE_MAX],
            'mappings.*.unchecked_value' => ['nullable', 'string', 'max:'.self::MAPPED_VALUE_MAX],
        ];
    }

    public function typeId(): int
    {
        return $this->integer('type_id');
    }

    public function name(): string
    {
        return $this->string('name')->squish()->toString();
    }

    public function kind(): FormTemplateKind
    {
        $template = $this->template();

        return $template === null ? FormTemplateKind::from($this->string('kind')->toString()) : $template->kind;
    }

    public function pdf(): ?UploadedFile
    {
        $file = $this->file('pdf');

        return $file instanceof UploadedFile ? $file : null;
    }

    public function content(): TemplateContent
    {
        $fields = [];
        foreach ((array) $this->input('fields', []) as $field) {
            $type = FormInputType::from((string) $field['input_type']);
            $fields[] = new FieldDraft(
                (string) $field['key'],
                trim((string) $field['label']),
                $type,
                filter_var($field['is_required'] ?? false, FILTER_VALIDATE_BOOL),
                $type === FormInputType::Table && isset($field['picklist_id']) ? (int) $field['picklist_id'] : null,
            );
        }

        $mappings = [];
        foreach ((array) $this->input('mappings', []) as $mapping) {
            $mappings[] = new PdfMappingDraft(
                (string) $mapping['pdf_field'],
                $this->optional($mapping['token'] ?? null),
                $this->optional($mapping['checked_value'] ?? null),
                $this->optional($mapping['unchecked_value'] ?? null),
            );
        }

        return new TemplateContent($this->kind() === FormTemplateKind::RichText ? $this->string('body')->toString() : null, $fields, $mappings);
    }

    private function optional(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function template(): ?FormTemplate
    {
        $template = $this->route('template');

        return $template instanceof FormTemplate ? $template : null;
    }
}
