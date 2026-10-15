<?php

namespace Tests\Concerns;

use App\Domain\Forms\FieldDraft;
use App\Domain\Forms\PdfMappingDraft;
use App\Domain\Forms\TemplateContent;
use App\Enums\FormInputType;
use App\Enums\FormTemplateKind;
use App\Enums\LookupType;
use App\Models\FormTemplate;
use App\Models\User;
use App\Services\Forms\FormTemplateService;
use Illuminate\Http\UploadedFile;
use Tests\Support\FillablePdf;

trait CreatesForms
{
    use CreatesResidents;

    protected const string BODY = '<p>This certifies that {{resident.full_name}} of {{resident.address}} asked for this on {{document.date}} for {{field.purpose}}.</p>';

    /**
     * @param  list<FieldDraft>|null  $fields
     */
    protected function richTemplate(User $actor, ?array $fields = null, string $body = self::BODY, string $name = 'Certificate of residency'): FormTemplate
    {
        $fields ??= [new FieldDraft('purpose', 'Purpose', FormInputType::Text, true, null)];

        return app(FormTemplateService::class)->create(
            $this->lookup(LookupType::FormTemplateType)->id,
            $name,
            FormTemplateKind::RichText,
            new TemplateContent($body, $fields),
            null,
            (int) $actor->barangay_id,
            $actor,
        );
    }

    /**
     * @param  list<FieldDraft>  $fields
     * @param  list<PdfMappingDraft>  $mappings
     */
    protected function pdfTemplate(User $actor, array $fields, array $mappings, string $name = 'Permit application'): FormTemplate
    {
        return app(FormTemplateService::class)->create(
            $this->lookup(LookupType::FormTemplateType)->id,
            $name,
            FormTemplateKind::Pdf,
            new TemplateContent(null, $fields, $mappings),
            $this->pdfUpload(),
            (int) $actor->barangay_id,
            $actor,
        );
    }

    protected function pdfUpload(string $name = 'permit.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, FillablePdf::make(['applicant', 'purpose', 'paid_text'], ['is_paid']));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function richPayload(int $typeId, array $overrides = []): array
    {
        return [
            'type_id' => $typeId,
            'name' => 'Barangay certification',
            'kind' => FormTemplateKind::RichText->value,
            'body' => self::BODY,
            'fields' => [['key' => 'purpose', 'label' => 'Purpose', 'input_type' => 'text', 'is_required' => true]],
            ...$overrides,
        ];
    }
}
