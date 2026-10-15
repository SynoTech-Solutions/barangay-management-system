<?php

namespace App\Services\ImportBundle\Importers;

use App\Domain\Forms\Pdf\AcroFormReader;
use App\Domain\Forms\Pdf\UnreadablePdf;
use App\Domain\Forms\RichTextSanitizer;
use App\Enums\BundleEntity;
use App\Enums\DocumentKind;
use App\Enums\FormInputType;
use App\Enums\FormTemplateKind;
use App\Enums\ImportProblem;
use App\Enums\LookupType;
use App\Http\Requests\Forms\FormTemplateRequest;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\Record;
use App\Services\ImportBundle\TableWriter;
use App\Support\StoragePaths;
use Illuminate\Support\Facades\DB;

final class FormTemplatesImporter extends BaseImporter
{
    public const string TABLE = 'form_templates';

    public const string VERSION_TABLE = 'form_template_versions';

    public const string FIELD_TABLE = 'form_template_fields';

    public const string MAPPING_TABLE = 'form_pdf_mappings';

    private const int TEMPLATE_CHUNK_SIZE = 50;

    public function entity(): BundleEntity
    {
        return BundleEntity::FormTemplates;
    }

    public function chunkSize(): int
    {
        return self::TEMPLATE_CHUNK_SIZE;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        foreach ($records as $record) {
            if ($this->alreadyImported($context, $record)) {
                $this->observe($context, $record, 'source_number', DocumentKind::FormTemplate, null);

                continue;
            }

            $kind = FormTemplateKind::from($record->requiredText('kind'));
            $versions = $record->records('versions');
            $numbers = array_map(fn (Record $version): int => (int) $version->int('version'), $versions);
            if (count(array_unique($numbers)) !== count($numbers)) {
                $context->failed($record->key(), ImportProblem::DuplicateVersion);

                continue;
            }

            usort($versions, fn (Record $a, Record $b): int => $a->int('version') <=> $b->int('version'));
            $prepared = $this->prepare($context, $record, $kind, $versions);
            if ($prepared === null) {
                continue;
            }

            $templateId = TableWriter::reserveId(self::TABLE);
            [$number, $sourceNumber] = $this->number($context, $record, 'source_number', self::TABLE, 'template_number', DocumentKind::FormTemplate, $context->now->year);
            TableWriter::insert(self::TABLE, [[
                'id' => $templateId,
                'barangay_id' => $context->barangayId(),
                'template_number' => $number,
                'source_number' => $sourceNumber,
                'import_note' => $this->note($record),
                'type_id' => $context->lookups->findOrCreate(LookupType::FormTemplateType, $record->requiredText('type')),
                'name' => $this->uniqueName($context, $record, self::TABLE, 'name', $record->requiredText('name'), FormTemplateRequest::NAME_MAX),
                'kind' => $kind->value,
                'is_active' => $record->bool('is_active', true),
                'current_version_id' => null,
                'created_at' => $context->timestamp,
                'updated_at' => $context->timestamp,
            ]]);

            $currentId = null;
            foreach ($prepared as $entry) {
                $currentId = $this->version($context, $templateId, $entry);
            }
            DB::table(self::TABLE)->where('id', $templateId)->update(['current_version_id' => $currentId]);

            $this->remember($context, $record, self::TABLE, $templateId);
        }
    }

    /**
     * @param  list<Record>  $versions
     * @return list<array{version: Record, fields: list<array<string, mixed>>, pdf: array{bytes: string, mime: string, extension: string}|null}>|null
     */
    private function prepare(ImportContext $context, Record $record, FormTemplateKind $kind, array $versions): ?array
    {
        $prepared = [];
        foreach ($versions as $version) {
            $fields = [];
            $keys = [];
            foreach ($version->records('fields') as $position => $field) {
                $key = $field->requiredText('key');
                if (isset($keys[$key])) {
                    $context->failed($record->key(), ImportProblem::DuplicateFieldKey);

                    return null;
                }
                $keys[$key] = true;

                $type = FormInputType::from($field->requiredText('input_type'));
                $picklistId = null;
                if ($type === FormInputType::Table) {
                    $picklistId = $this->required($context, $field, 'picklist_key', BundleEntity::FormPicklists, $record->key());
                    if ($picklistId === null) {
                        return null;
                    }
                }

                $fields[] = [
                    'key' => $key,
                    'label' => $field->requiredText('label'),
                    'input_type' => $type->value,
                    'is_required' => $field->bool('is_required'),
                    'position' => $position + 1,
                    'form_picklist_id' => $picklistId,
                ];
            }

            $pdf = null;
            if ($kind === FormTemplateKind::Pdf) {
                $pdf = $this->loadAttachment($context, $record, $version->raw('pdf_file'), self::PDF_TYPES, FormTemplateRequest::PDF_MAX_KILOBYTES, true);
                if ($pdf === null) {
                    return null;
                }

                try {
                    AcroFormReader::read($pdf['bytes']);
                } catch (UnreadablePdf) {
                    $context->failed($record->key(), ImportProblem::AttachmentUnsupported);

                    return null;
                }
            }

            $prepared[] = ['version' => $version, 'fields' => $fields, 'pdf' => $pdf];
        }

        return $prepared;
    }

    /**
     * @param  array{version: Record, fields: list<array<string, mixed>>, pdf: array{bytes: string, mime: string, extension: string}|null}  $entry
     */
    private function version(ImportContext $context, int $templateId, array $entry): int
    {
        $version = $entry['version'];
        $stored = $entry['pdf'] === null ? null : $this->storeAttachment(
            $context,
            $entry['pdf'],
            fn (string $extension): string => StoragePaths::formTemplatePdf($context->barangayId(), $this->token()),
        );
        $body = $version->raw('body');

        $versionId = TableWriter::reserveId(self::VERSION_TABLE);
        TableWriter::insert(self::VERSION_TABLE, [[
            'id' => $versionId,
            'barangay_id' => $context->barangayId(),
            'form_template_id' => $templateId,
            'version' => (int) $version->int('version'),
            'body' => $body === null ? null : RichTextSanitizer::clean($body),
            'pdf_path' => $stored['path'] ?? null,
            'pdf_file_name' => $stored === null ? null : ($version->text('pdf_file_name') ?? basename((string) $version->raw('pdf_file'))),
            'pdf_size_bytes' => $stored['size'] ?? null,
            'pdf_sha256' => $stored['sha256'] ?? null,
            'created_at' => $context->utcOrNow($version->raw('created_at')),
        ]]);

        TableWriter::insert(self::FIELD_TABLE, array_map(fn (array $field): array => [
            ...$field,
            'barangay_id' => $context->barangayId(),
            'form_template_version_id' => $versionId,
        ], $entry['fields']));

        TableWriter::insert(self::MAPPING_TABLE, array_map(fn (Record $mapping): array => [
            'barangay_id' => $context->barangayId(),
            'form_template_version_id' => $versionId,
            'pdf_field' => $mapping->requiredText('pdf_field'),
            'pdf_field_type' => $mapping->requiredText('pdf_field_type'),
            'pdf_on_state' => $mapping->text('pdf_on_state'),
            'token' => $mapping->text('token'),
            'checked_value' => $mapping->text('checked_value'),
            'unchecked_value' => $mapping->text('unchecked_value'),
        ], $version->records('pdf_mappings')));

        return $versionId;
    }
}
