<?php

namespace App\Services\ImportBundle\Importers;

use App\Domain\Forms\FormSnapshot;
use App\Domain\Forms\SnapshotField;
use App\Enums\BundleEntity;
use App\Enums\DocumentKind;
use App\Enums\FormInputType;
use App\Enums\ImportProblem;
use App\Enums\ReceiptDocumentType;
use App\Models\Resident;
use App\Services\Forms\FormTokenResolver;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\Record;
use App\Services\ImportBundle\TableWriter;
use App\Services\Numbering\DocumentNumberAllocator;
use Illuminate\Support\Facades\DB;

final class FormTransactionsImporter extends BaseImporter
{
    public const string TABLE = 'form_transactions';

    public function __construct(DocumentNumberAllocator $numbers, private readonly FormTokenResolver $tokens)
    {
        parent::__construct($numbers);
    }

    public function entity(): BundleEntity
    {
        return BundleEntity::FormTransactions;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        foreach ($records as $record) {
            $year = $context->year($record->raw('issued_at'));
            if ($this->alreadyImported($context, $record)) {
                $this->observe($context, $record, 'source_number', DocumentKind::FormTransaction, $year);

                continue;
            }

            $templateId = $this->required($context, $record, 'template_key', BundleEntity::FormTemplates);
            if ($templateId === null) {
                continue;
            }

            $residentId = $this->required($context, $record, 'resident_key', BundleEntity::Residents);
            if ($residentId === null) {
                continue;
            }

            $versionId = DB::table(FormTemplatesImporter::VERSION_TABLE)
                ->where('barangay_id', $context->barangayId())
                ->where('form_template_id', $templateId)
                ->where('version', (int) $record->int('template_version'))
                ->value('id');
            if ($versionId === null) {
                $context->failed($record->key(), ImportProblem::UnknownVersion);

                continue;
            }

            $id = TableWriter::reserveId(self::TABLE);
            $lifecycle = $this->lifecycle($context, $record);
            [$number, $sourceNumber] = $this->number($context, $record, 'source_number', self::TABLE, 'transaction_number', DocumentKind::FormTransaction, $year);
            $receipt = $this->receipt($context, $record, ReceiptDocumentType::FormTransaction, $id, $lifecycle['cancelled_at']);
            $resident = Resident::withTrashed()->findOrFail($residentId);
            $snapshot = new FormSnapshot(
                array_map(fn (Record $field): SnapshotField => $this->field($field), $record->records('fields')),
                $this->tokens->snapshot($resident, $context->local($record->raw('issued_at'))),
            );

            TableWriter::insert(self::TABLE, [[
                ...$lifecycle,
                ...$this->signatories($record),
                'id' => $id,
                'barangay_id' => $context->barangayId(),
                'transaction_number' => $number,
                'source_number' => $sourceNumber,
                'import_note' => $this->note($record, $receipt['note']),
                'or_number' => $receipt['or_number'],
                'form_template_id' => $templateId,
                'form_template_version_id' => (int) $versionId,
                'resident_id' => $residentId,
                'amount' => $record->money('amount'),
                'snapshot' => json_encode($snapshot->toArray(), JSON_THROW_ON_ERROR),
            ]]);

            $this->remember($context, $record, self::TABLE, $id);
        }
    }

    private function field(Record $field): SnapshotField
    {
        $value = $field->scalar('value');

        return new SnapshotField(
            $field->requiredText('key'),
            $field->requiredText('label'),
            FormInputType::tryFrom((string) $field->text('input_type')) ?? FormInputType::Text,
            $value,
            $field->raw('display') ?? (is_bool($value) ? ($value ? '1' : '') : (string) $value),
        );
    }
}
