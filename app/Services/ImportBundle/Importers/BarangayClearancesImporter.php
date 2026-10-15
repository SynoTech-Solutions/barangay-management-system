<?php

namespace App\Services\ImportBundle\Importers;

use App\Domain\Certificates\ClearanceExpiry;
use App\Enums\BundleEntity;
use App\Enums\ClearanceValidityMode;
use App\Enums\DocumentKind;
use App\Enums\LookupType;
use App\Enums\ReceiptDocumentType;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\TableWriter;
use Illuminate\Support\Facades\DB;

final class BarangayClearancesImporter extends BaseImporter
{
    public const string TABLE = 'barangay_clearances';

    public function entity(): BundleEntity
    {
        return BundleEntity::BarangayClearances;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        $ids = TableWriter::reserveIds(self::TABLE, count($records));
        $rows = [];
        [$mode, $months] = $this->validity($context);

        foreach ($records as $index => $record) {
            $year = $context->year($record->raw('issued_at'));
            if ($this->alreadyImported($context, $record)) {
                $this->observe($context, $record, 'source_number', DocumentKind::BarangayClearance, $year);
                $this->observe($context, $record, 'source_control_number', DocumentKind::BarangayClearanceControl, $year);

                continue;
            }

            $residentId = $this->required($context, $record, 'resident_key', BundleEntity::Residents);
            if ($residentId === null) {
                continue;
            }

            $id = $ids[$index];
            $lifecycle = $this->lifecycle($context, $record);
            [$number, $sourceNumber] = $this->number($context, $record, 'source_number', self::TABLE, 'clearance_number', DocumentKind::BarangayClearance, $year);
            [$control, $sourceControl] = $this->number($context, $record, 'source_control_number', self::TABLE, 'control_number', DocumentKind::BarangayClearanceControl, $year);
            $receipt = $this->receipt($context, $record, ReceiptDocumentType::BarangayClearance, $id, $lifecycle['cancelled_at']);

            $rows[] = [
                ...$lifecycle,
                ...$this->signatories($record),
                'id' => $id,
                'barangay_id' => $context->barangayId(),
                'resident_id' => $residentId,
                'clearance_number' => $number,
                'control_number' => $control,
                'source_number' => $sourceControl ?? $sourceNumber,
                'import_note' => $this->note($record, $receipt['note']),
                'or_number' => $receipt['or_number'],
                'clearance_type_id' => $context->lookups->findOrCreate(LookupType::ClearanceType, $record->requiredText('clearance_type')),
                'purpose_id' => $context->lookups->findOrCreate(LookupType::ClearancePurpose, $record->requiredText('purpose')),
                'amount' => $record->money('amount'),
                'doc_stamp' => $record->money('doc_stamp'),
                'ctc_number' => $record->text('ctc_number'),
                'ctc_place' => $record->text('ctc_place'),
                'ctc_date' => $record->raw('ctc_date'),
                'expires_on' => $record->raw('expires_on') ?? ClearanceExpiry::until($context->local($record->raw('issued_at')), $mode, $months)->toDateString(),
            ];
            $this->remember($context, $record, self::TABLE, $id);
        }

        TableWriter::insert(self::TABLE, $rows);
    }

    /**
     * @return array{0: ClearanceValidityMode, 1: int|null}
     */
    private function validity(ImportContext $context): array
    {
        $profile = DB::table('barangay_profiles')->where('barangay_id', $context->barangayId())->first(['clearance_validity_mode', 'clearance_validity_months']);
        $mode = ClearanceValidityMode::tryFrom((string) ($profile->clearance_validity_mode ?? '')) ?? ClearanceValidityMode::EndOfYear;

        return [$mode, isset($profile->clearance_validity_months) ? (int) $profile->clearance_validity_months : null];
    }
}
