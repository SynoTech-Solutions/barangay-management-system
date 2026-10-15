<?php

namespace App\Services\Forms;

use App\Domain\Forms\FieldDraft;
use App\Domain\Forms\Placeholder;
use App\Domain\Forms\PlaceholderCatalog;
use App\Domain\Forms\RichTextSanitizer;
use App\Domain\Forms\TemplateContent;
use App\Enums\AuditAction;
use App\Enums\DocumentKind;
use App\Enums\ErrorCode;
use App\Enums\FormTemplateKind;
use App\Exceptions\BmsException;
use App\Models\FormPdfMapping;
use App\Models\FormTemplate;
use App\Models\FormTemplateField;
use App\Models\FormTemplateVersion;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use App\Services\Features\FeatureFlags;
use App\Services\Forms\Pdf\FormPdfStore;
use App\Services\Forms\Pdf\StoredPdf;
use App\Services\Numbering\DocumentNumberAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class FormTemplateService
{
    private const string TOKEN_SEPARATOR = ', ';

    public function __construct(
        private AuditTrail $audit,
        private DocumentNumberAllocator $numbers,
        private FeatureFlags $features,
        private FormPdfStore $pdfs,
    ) {}

    public function ensureKindEnabled(FormTemplateKind $kind): void
    {
        if (! $this->features->enabled($kind->flag())) {
            throw BmsException::of(ErrorCode::FeatureDisabled);
        }
    }

    public function create(int $typeId, string $name, FormTemplateKind $kind, TemplateContent $content, ?UploadedFile $pdf, int $barangayId, User $actor): FormTemplate
    {
        $this->ensureKindEnabled($kind);
        $stored = $kind === FormTemplateKind::Pdf && $pdf !== null ? $this->pdfs->store($barangayId, $pdf) : null;
        if ($kind === FormTemplateKind::Pdf && $stored === null) {
            throw BmsException::of(ErrorCode::PdfFormUnreadable);
        }

        try {
            return DB::transaction(function () use ($typeId, $name, $kind, $content, $stored, $actor): FormTemplate {
                $this->ensureNameAvailable($name, null);
                $content = $this->prepare($kind, $content, $stored);

                $template = new FormTemplate(['type_id' => $typeId, 'name' => $name, 'kind' => $kind]);
                $template->template_number = $this->numbers->next(DocumentKind::FormTemplate, CarbonImmutable::now()->year);
                $template->is_active = true;
                $template->created_by = $actor->id;
                $template->updated_by = $actor->id;
                $template->save();

                $version = $this->writeVersion($template, 1, $content, $stored, $actor);
                $template->current_version_id = $version->id;
                $template->save();

                $this->audit->record(AuditAction::FormTemplateCreated, $template, after: [
                    'template_number' => $template->template_number,
                    'name' => $template->name,
                    'type_id' => $template->type_id,
                    'kind' => $kind->value,
                    'version' => $version->version,
                    'field_keys' => $content->fieldKeys(),
                ], barangayId: $template->barangay_id, actorId: $actor->id);

                return $template;
            });
        } catch (Throwable $exception) {
            $this->pdfs->discard($stored);

            throw $exception;
        }
    }

    public function update(FormTemplate $template, int $typeId, string $name, TemplateContent $content, ?UploadedFile $pdf, User $actor): ?FormTemplateVersion
    {
        $this->ensureKindEnabled($template->kind);
        $stored = null;
        if ($template->kind === FormTemplateKind::Pdf) {
            $current = $template->currentVersion;
            $stored = $pdf !== null ? $this->pdfs->store($template->barangay_id, $pdf) : ($current === null ? null : $this->pdfs->existing($current));
            if ($stored === null) {
                throw BmsException::of(ErrorCode::PdfFormUnreadable);
            }
        }

        try {
            $version = DB::transaction(function () use ($template, $typeId, $name, $content, $stored, $actor): ?FormTemplateVersion {
                $locked = FormTemplate::query()->lockForUpdate()->findOrFail($template->id);
                $this->ensureNameAvailable($name, $locked->id);
                $content = $this->prepare($locked->kind, $content, $stored);

                $before = ['name' => $locked->name, 'type_id' => $locked->type_id];
                $locked->name = $name;
                $locked->type_id = $typeId;
                $locked->updated_by = $actor->id;

                $current = $locked->current_version_id === null ? null : FormTemplateVersion::query()->with(['fields', 'mappings'])->find($locked->current_version_id);
                $version = null;
                if ($current === null || $this->fingerprint($content, $stored) !== $this->versionFingerprint($current)) {
                    $number = (int) FormTemplateVersion::query()->where('form_template_id', $locked->id)->max('version') + 1;
                    $version = $this->writeVersion($locked, $number, $content, $stored, $actor);
                    $locked->current_version_id = $version->id;
                }
                $locked->save();

                $after = ['name' => $locked->name, 'type_id' => $locked->type_id];
                if ($before !== $after) {
                    $this->audit->record(AuditAction::FormTemplateUpdated, $locked, $before, $after, $locked->barangay_id, $actor->id);
                }

                if ($version !== null) {
                    $this->audit->record(AuditAction::FormTemplateVersioned, $locked, after: [
                        'version' => $version->version,
                        'field_keys' => $content->fieldKeys(),
                    ], barangayId: $locked->barangay_id, actorId: $actor->id);
                }

                return $version;
            });
        } catch (Throwable $exception) {
            $this->pdfs->discard($stored);

            throw $exception;
        }

        if ($version === null) {
            $this->pdfs->discard($stored);
        }

        return $version;
    }

    public function setActive(FormTemplate $template, bool $active, User $actor): void
    {
        DB::transaction(function () use ($template, $active, $actor): void {
            $locked = FormTemplate::query()->lockForUpdate()->findOrFail($template->id);
            if ($locked->is_active === $active) {
                return;
            }

            $locked->is_active = $active;
            $locked->updated_by = $actor->id;
            $locked->save();

            $this->audit->record(
                $active ? AuditAction::FormTemplateReactivated : AuditAction::FormTemplateDeactivated,
                $locked,
                ['is_active' => ! $active],
                ['is_active' => $active],
                $locked->barangay_id,
                $actor->id,
            );
        });
    }

    private function prepare(FormTemplateKind $kind, TemplateContent $content, ?StoredPdf $stored): TemplateContent
    {
        $fieldKeys = $content->fieldKeys();

        if ($kind === FormTemplateKind::RichText) {
            $body = RichTextSanitizer::clean((string) $content->body);
            $this->ensureKnownTokens(Placeholder::unknownTokens($body, $fieldKeys));

            return new TemplateContent($body, $content->fields);
        }

        $known = array_flip([...PlaceholderCatalog::tokens(), ...array_map(Placeholder::field(...), $fieldKeys)]);
        $unknown = [];
        $mappings = [];
        foreach ($content->mappings as $mapping) {
            if ($stored?->field($mapping->pdfField) === null) {
                continue;
            }
            if ($mapping->token !== null && ! isset($known[$mapping->token])) {
                $unknown[] = $mapping->token;
            }
            $mappings[] = $mapping;
        }
        $this->ensureKnownTokens($unknown);

        return new TemplateContent(null, $content->fields, $mappings);
    }

    /**
     * @param  list<string>  $unknown
     */
    private function ensureKnownTokens(array $unknown): void
    {
        if ($unknown !== []) {
            throw BmsException::of(ErrorCode::FormTemplateUnknownTokens, ['tokens' => implode(self::TOKEN_SEPARATOR, array_unique($unknown))]);
        }
    }

    private function ensureNameAvailable(string $name, ?int $exceptId): void
    {
        $taken = FormTemplate::query()
            ->whereRaw('lower(name) = lower(?)', [$name])
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($taken) {
            throw BmsException::of(ErrorCode::FormTemplateNameTaken, ['name' => $name]);
        }
    }

    private function writeVersion(FormTemplate $template, int $number, TemplateContent $content, ?StoredPdf $stored, User $actor): FormTemplateVersion
    {
        $version = new FormTemplateVersion([
            'version' => $number,
            'body' => $content->body,
            'pdf_path' => $stored?->path,
            'pdf_file_name' => $stored?->fileName,
            'pdf_size_bytes' => $stored?->sizeBytes,
            'pdf_sha256' => $stored?->sha256,
        ]);
        $version->barangay_id = $template->barangay_id;
        $version->form_template_id = $template->id;
        $version->created_by = $actor->id;
        $version->created_at = CarbonImmutable::now();
        $version->save();

        foreach ($content->fields as $position => $draft) {
            $field = new FormTemplateField([...$draft->toArray(), 'position' => $position + 1]);
            $field->barangay_id = $template->barangay_id;
            $field->form_template_version_id = $version->id;
            $field->save();
        }

        foreach ($content->mappings as $draft) {
            $pdfField = $stored?->field($draft->pdfField);
            if ($pdfField === null) {
                continue;
            }

            $mapping = new FormPdfMapping([
                'pdf_field' => $pdfField->name,
                'pdf_field_type' => $pdfField->type,
                'pdf_on_state' => $pdfField->onState,
                'token' => $draft->token,
                'checked_value' => $draft->checkedValue,
                'unchecked_value' => $draft->uncheckedValue,
            ]);
            $mapping->barangay_id = $template->barangay_id;
            $mapping->form_template_version_id = $version->id;
            $mapping->save();
        }

        return $version;
    }

    /**
     * @return array<string, mixed>
     */
    private function fingerprint(TemplateContent $content, ?StoredPdf $stored): array
    {
        $mappings = [];
        foreach ($content->mappings as $mapping) {
            $mappings[$mapping->pdfField] = [$mapping->token, $mapping->checkedValue, $mapping->uncheckedValue];
        }
        ksort($mappings);

        return [
            'body' => $content->body,
            'pdf' => $stored?->sha256,
            'fields' => array_map(fn (FieldDraft $field): array => $field->toArray(), $content->fields),
            'mappings' => $mappings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function versionFingerprint(FormTemplateVersion $version): array
    {
        $mappings = [];
        foreach ($version->mappings as $mapping) {
            $mappings[$mapping->pdf_field] = [$mapping->token, $mapping->checked_value, $mapping->unchecked_value];
        }
        ksort($mappings);

        return [
            'body' => $version->body,
            'pdf' => $version->pdf_sha256,
            'fields' => array_values($version->fields->map(fn (FormTemplateField $field): array => [
                'key' => $field->key,
                'label' => $field->label,
                'input_type' => $field->input_type->value,
                'is_required' => $field->is_required,
                'form_picklist_id' => $field->form_picklist_id,
            ])->all()),
            'mappings' => $mappings,
        ];
    }
}
