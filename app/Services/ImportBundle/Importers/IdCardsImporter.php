<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Enums\DocumentKind;
use App\Enums\ReceiptDocumentType;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\TableWriter;

final class IdCardsImporter extends BaseImporter
{
    public const string TABLE = 'id_cards';

    public function entity(): BundleEntity
    {
        return BundleEntity::IdCards;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        $ids = TableWriter::reserveIds(self::TABLE, count($records));
        $rows = [];

        foreach ($records as $index => $record) {
            if ($this->alreadyImported($context, $record)) {
                $this->observe($context, $record, 'source_number', DocumentKind::IdCard, $context->year($record->raw('issued_at')));

                continue;
            }

            $residentId = $this->required($context, $record, 'resident_key', BundleEntity::Residents);
            if ($residentId === null) {
                continue;
            }

            $id = $ids[$index];
            $lifecycle = $this->lifecycle($context, $record);
            [$number, $sourceNumber] = $this->number($context, $record, 'source_number', self::TABLE, 'card_number', DocumentKind::IdCard, $context->year($record->raw('issued_at')));
            $receipt = $this->receipt($context, $record, ReceiptDocumentType::IdCard, $id, $lifecycle['cancelled_at']);

            $rows[] = [
                ...$lifecycle,
                ...$this->signatories($record),
                'id' => $id,
                'barangay_id' => $context->barangayId(),
                'resident_id' => $residentId,
                'card_number' => $number,
                'source_number' => $sourceNumber,
                'import_note' => $this->note($record, $receipt['note']),
                'or_number' => $receipt['or_number'],
                'ctc_number' => $record->text('ctc_number'),
                'ctc_place' => $record->text('ctc_place'),
                'ctc_date' => $record->raw('ctc_date'),
                'amount' => $record->money('amount'),
            ];
            $this->remember($context, $record, self::TABLE, $id);
        }

        TableWriter::insert(self::TABLE, $rows);
    }
}
