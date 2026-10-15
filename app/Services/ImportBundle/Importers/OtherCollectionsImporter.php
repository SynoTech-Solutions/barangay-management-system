<?php

namespace App\Services\ImportBundle\Importers;

use App\Domain\Money\OrNumber;
use App\Enums\BundleEntity;
use App\Enums\DocumentKind;
use App\Enums\ImportProblem;
use App\Enums\ReceiptDocumentType;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\TableWriter;

final class OtherCollectionsImporter extends BaseImporter
{
    public const string TABLE = 'other_collections';

    public function entity(): BundleEntity
    {
        return BundleEntity::OtherCollections;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        $ids = TableWriter::reserveIds(self::TABLE, count($records));
        $rows = [];

        foreach ($records as $index => $record) {
            $year = $context->year($record->raw('recorded_at'));
            if ($this->alreadyImported($context, $record)) {
                $this->observe($context, $record, 'source_number', DocumentKind::OtherCollection, $year);

                continue;
            }

            $typeId = $this->required($context, $record, 'collection_type_key', BundleEntity::CollectionTypes);
            if ($typeId === null) {
                continue;
            }

            $id = $ids[$index];
            $recordedAt = $context->utcOrNow($record->raw('recorded_at'));
            $cancelledAt = $record->raw('cancelled_at') === null ? null : $context->utc($record->raw('cancelled_at'));
            $orNumber = (string) OrNumber::normalize($record->text('or_number'));
            if (! $context->receipts->claim(ReceiptDocumentType::OtherCollection, $id, $orNumber, $cancelledAt)) {
                $context->failed($record->key(), ImportProblem::OrNumberConflict);

                continue;
            }

            [$number, $sourceNumber] = $this->number($context, $record, 'source_number', self::TABLE, 'collection_number', DocumentKind::OtherCollection, $year);

            $rows[] = [
                'id' => $id,
                'barangay_id' => $context->barangayId(),
                'collection_number' => $number,
                'source_number' => $sourceNumber,
                'import_note' => $this->note($record),
                'collection_type_id' => $typeId,
                'resident_id' => $this->optional($context, $record, 'resident_key', BundleEntity::Residents),
                'payer_name' => $record->requiredText('payer_name'),
                'or_number' => $orNumber,
                'amount' => $record->money('amount'),
                'paid_on' => $record->raw('paid_on') ?? $context->local($record->raw('recorded_at'))->toDateString(),
                'remarks' => $record->text('remarks'),
                'recorded_at' => $recordedAt,
                'cancelled_at' => $cancelledAt,
                'cancel_reason' => $cancelledAt === null ? null : $this->cancelReason($record),
                'created_at' => $recordedAt,
                'updated_at' => $context->timestamp,
            ];
            $this->remember($context, $record, self::TABLE, $id);
        }

        TableWriter::insert(self::TABLE, $rows);
    }
}
