<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Enums\DocumentKind;
use App\Enums\ReceiptDocumentType;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\TableWriter;
use Illuminate\Support\Facades\DB;

final class BusinessClearancesImporter extends BaseImporter
{
    public const string TABLE = 'business_clearances';

    public function entity(): BundleEntity
    {
        return BundleEntity::BusinessClearances;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        $ids = TableWriter::reserveIds(self::TABLE, count($records));
        $rows = [];

        foreach ($records as $index => $record) {
            $year = $context->year($record->raw('issued_at'));
            if ($this->alreadyImported($context, $record)) {
                $this->observe($context, $record, 'source_number', DocumentKind::BusinessClearance, $year);
                $this->observe($context, $record, 'source_control_number', DocumentKind::BusinessClearanceControl, $year);

                continue;
            }

            $businessId = $this->required($context, $record, 'business_key', BundleEntity::Businesses);
            if ($businessId === null) {
                continue;
            }

            $business = DB::table(BusinessesImporter::TABLE)
                ->where('barangay_id', $context->barangayId())
                ->where('id', $businessId)
                ->first(['name', 'street_address', 'owner_resident_id', 'owner_name']);
            $ownerId = $this->optional($context, $record, 'owner_resident_key', BundleEntity::Residents) ?? $business?->owner_resident_id;
            $ownerName = $record->text('owner_name')
                ?? ($ownerId === null ? $business?->owner_name : DB::table(ResidentsImporter::TABLE)->where('id', $ownerId)->value('full_name'));

            $id = $ids[$index];
            $lifecycle = $this->lifecycle($context, $record);
            [$number, $sourceNumber] = $this->number($context, $record, 'source_number', self::TABLE, 'clearance_number', DocumentKind::BusinessClearance, $year);
            [$control, $sourceControl] = $this->number($context, $record, 'source_control_number', self::TABLE, 'control_number', DocumentKind::BusinessClearanceControl, $year);
            $receipt = $this->receipt($context, $record, ReceiptDocumentType::BusinessClearance, $id, $lifecycle['cancelled_at']);

            $rows[] = [
                ...$lifecycle,
                ...$this->signatories($record),
                'id' => $id,
                'barangay_id' => $context->barangayId(),
                'business_id' => $businessId,
                'clearance_number' => $number,
                'control_number' => $control,
                'source_number' => $sourceControl ?? $sourceNumber,
                'import_note' => $this->note($record, $receipt['note']),
                'or_number' => $receipt['or_number'],
                'business_name' => $record->text('business_name') ?? (string) $business?->name,
                'business_address' => $record->text('business_address') ?? $business?->street_address,
                'owner_resident_id' => $ownerId,
                'owner_name' => is_string($ownerName) ? $ownerName : '',
                'amount' => $record->money('amount'),
                'doc_stamp' => $record->money('doc_stamp'),
                'ctc_number' => $record->text('ctc_number'),
                'ctc_place' => $record->text('ctc_place'),
                'ctc_date' => $record->raw('ctc_date'),
                'expires_on' => $record->raw('expires_on') ?? $context->local($record->raw('issued_at'))->endOfYear()->toDateString(),
            ];
            $this->remember($context, $record, self::TABLE, $id);
        }

        TableWriter::insert(self::TABLE, $rows);
    }
}
