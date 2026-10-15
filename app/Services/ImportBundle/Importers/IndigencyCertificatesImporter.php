<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Enums\DocumentKind;
use App\Enums\LookupType;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\TableWriter;

final class IndigencyCertificatesImporter extends BaseImporter
{
    public const string TABLE = 'indigency_certificates';

    public function entity(): BundleEntity
    {
        return BundleEntity::IndigencyCertificates;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        $ids = TableWriter::reserveIds(self::TABLE, count($records));
        $rows = [];

        foreach ($records as $index => $record) {
            $year = $context->year($record->raw('issued_at'));
            if ($this->alreadyImported($context, $record)) {
                $this->observe($context, $record, 'source_number', DocumentKind::IndigencyCertificate, $year);

                continue;
            }

            $residentId = $this->required($context, $record, 'resident_key', BundleEntity::Residents);
            if ($residentId === null) {
                continue;
            }

            $id = $ids[$index];
            [$number, $sourceNumber] = $this->number($context, $record, 'source_number', self::TABLE, 'certificate_number', DocumentKind::IndigencyCertificate, $year);

            $rows[] = [
                ...$this->lifecycle($context, $record),
                ...$this->signatories($record),
                'id' => $id,
                'barangay_id' => $context->barangayId(),
                'resident_id' => $residentId,
                'certificate_number' => $number,
                'source_number' => $sourceNumber,
                'import_note' => $this->note($record),
                'purpose_id' => $context->lookups->findOrCreate(LookupType::CertificatePurpose, $record->requiredText('purpose')),
                'amount' => $record->money('amount'),
            ];
            $this->remember($context, $record, self::TABLE, $id);
        }

        TableWriter::insert(self::TABLE, $rows);
    }
}
