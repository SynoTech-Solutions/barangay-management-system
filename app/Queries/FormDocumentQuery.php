<?php

namespace App\Queries;

use App\Domain\Forms\FormSnapshot;
use App\Domain\Forms\PdfValueMapper;
use App\Domain\Forms\Placeholder;
use App\Domain\Forms\RenderedTemplate;
use App\Domain\Forms\RichTextSanitizer;
use App\Domain\Forms\SnapshotField;
use App\Domain\Forms\TemplateRenderer;
use App\Enums\FormInputType;
use App\Models\FormPdfMapping;
use App\Models\FormTemplateField;
use App\Models\FormTemplateVersion;
use App\Models\FormTransaction;
use App\Services\Forms\FormTokenResolver;
use App\Services\Forms\FormValueResolver;
use App\Services\Forms\Pdf\FormPdfStore;
use App\Services\Forms\Pdf\PdfFormFiller;
use Carbon\CarbonImmutable;

final readonly class FormDocumentQuery
{
    public function __construct(
        private FormTokenResolver $tokens,
        private FormValueResolver $values,
        private FormPdfStore $pdfs,
        private PdfFormFiller $filler,
        private DocumentPrintContext $context,
    ) {}

    public function snapshot(FormTransaction $transaction): FormSnapshot
    {
        return FormSnapshot::fromArray($transaction->snapshot);
    }

    /**
     * @return array<string, string>
     */
    public function values(FormTransaction $transaction): array
    {
        $snapshot = $this->snapshot($transaction);

        return $snapshot->placeholderValues($this->tokens->document(
            $snapshot,
            $transaction->transaction_number,
            $transaction->or_number,
            $transaction->amount,
            $transaction->issued_at,
        ));
    }

    public function html(FormTransaction $transaction): RenderedTemplate
    {
        $version = FormTemplateVersion::query()->findOrFail($transaction->form_template_version_id);

        return TemplateRenderer::render(RichTextSanitizer::clean((string) $version->body), $this->values($transaction));
    }

    /**
     * @return array<string, mixed>
     */
    public function printData(FormTransaction $transaction): array
    {
        $transaction->loadMissing(['template:id,name', 'version:id,version']);
        $rendered = $this->html($transaction);

        return [
            'document' => [
                'id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'template' => $transaction->template?->name,
                'version' => $transaction->version?->version,
                'html' => $rendered->html,
                'missing' => $rendered->missing,
                'print_count' => $transaction->print_count,
                'is_reprint' => $transaction->isReprint(),
            ],
            'header' => $this->context->header(),
        ];
    }

    public function pdf(FormTransaction $transaction): string
    {
        $version = FormTemplateVersion::query()->with('mappings')->findOrFail($transaction->form_template_version_id);
        $snapshot = $this->snapshot($transaction);

        return $this->filled($version, $this->values($transaction), $snapshot->fields);
    }

    public function previewHtml(string $body, CarbonImmutable $on): RenderedTemplate
    {
        $samples = $this->tokens->samples($on);
        foreach (Placeholder::tokensIn($body) as $token) {
            if (str_starts_with($token, Placeholder::FIELD_NAMESPACE.'.') && ! isset($samples[$token])) {
                $samples[$token] = (string) __('forms.samples.field', ['key' => substr($token, strlen(Placeholder::FIELD_NAMESPACE) + 1)]);
            }
        }

        return TemplateRenderer::render(RichTextSanitizer::clean($body), $samples);
    }

    public function previewPdf(FormTemplateVersion $version, CarbonImmutable $on): string
    {
        $version->loadMissing(['mappings', 'fields']);
        $samples = $this->tokens->samples($on);
        $fields = [];
        foreach ($version->fields as $field) {
            $fields[] = $this->sampleField($field);
            $samples[Placeholder::field($field->key)] = end($fields)->display;
        }

        return $this->filled($version, $samples, $fields);
    }

    /**
     * @param  array<string, string>  $values
     * @param  list<SnapshotField>  $fields
     */
    private function filled(FormTemplateVersion $version, array $values, array $fields): string
    {
        $raw = [];
        foreach ($fields as $field) {
            $raw[Placeholder::field($field->key)] = $field->inputType === FormInputType::Check ? $field->value === true : $field->value;
        }

        $pdfValues = [];
        foreach ($version->mappings as $mapping) {
            /** @var FormPdfMapping $mapping */
            if ($mapping->token === null) {
                continue;
            }

            $pdfValues[$mapping->pdf_field] = PdfValueMapper::value(
                $mapping->pdf_field_type,
                $raw[$mapping->token] ?? null,
                $values[$mapping->token] ?? '',
                $mapping->checked_value,
                $mapping->unchecked_value,
            );
        }

        return $this->filler->fill($this->pdfs->contents($version), $pdfValues);
    }

    private function sampleField(FormTemplateField $field): SnapshotField
    {
        $labels = $this->values->checkLabels();

        return match ($field->input_type) {
            FormInputType::Check => new SnapshotField($field->key, $field->label, $field->input_type, true, $labels->checked),
            default => new SnapshotField($field->key, $field->label, $field->input_type, null, (string) __('forms.samples.field', ['key' => $field->label])),
        };
    }
}
