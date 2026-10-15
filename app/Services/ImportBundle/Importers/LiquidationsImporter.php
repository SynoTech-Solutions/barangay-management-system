<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Enums\DocumentKind;
use App\Enums\ImportProblem;
use App\Enums\LiquidationStatus;
use App\Enums\ReceiptDocumentType;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\TableWriter;
use Illuminate\Support\Facades\DB;

final class LiquidationsImporter extends BaseImporter
{
    public const string TABLE = 'liquidations';

    public const string ITEM_TABLE = 'liquidation_items';

    public function entity(): BundleEntity
    {
        return BundleEntity::Liquidations;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        foreach ($records as $record) {
            $year = $context->year($record->raw('recorded_at'));
            if ($this->alreadyImported($context, $record)) {
                $this->observe($context, $record, 'source_number', DocumentKind::LiquidationReport, $year);

                continue;
            }

            $voucherId = $this->required($context, $record, 'voucher_key', BundleEntity::Vouchers);
            if ($voucherId === null) {
                continue;
            }

            if (! $context->unique->claim(self::TABLE, 'voucher_id', (string) $voucherId)) {
                $context->failed($record->key(), ImportProblem::LiquidationConflict);

                continue;
            }

            $id = TableWriter::reserveId(self::TABLE);
            $recordedAt = $context->utcOrNow($record->raw('recorded_at'));
            $status = LiquidationStatus::from($record->requiredText('status'));
            [$number, $sourceNumber] = $this->number($context, $record, 'source_number', self::TABLE, 'liquidation_number', DocumentKind::LiquidationReport, $year);
            $receipt = $this->receipt($context, $record, ReceiptDocumentType::LiquidationRefund, $id, null);

            TableWriter::insert(self::TABLE, [[
                'id' => $id,
                'barangay_id' => $context->barangayId(),
                'voucher_id' => $voucherId,
                'liquidation_number' => $number,
                'source_number' => $sourceNumber,
                'import_note' => $this->note($record, $receipt['note']),
                'liquidated_on' => $record->raw('liquidated_on') ?? $context->local($record->raw('recorded_at'))->toDateString(),
                'or_number' => $receipt['or_number'],
                'or_date' => $record->raw('or_date'),
                'status' => $status->value,
                'recorded_at' => $recordedAt,
                'cleared_at' => $status === LiquidationStatus::Cleared ? ($context->utc($record->raw('cleared_at')) ?? $recordedAt) : null,
                'created_at' => $recordedAt,
                'updated_at' => $context->timestamp,
            ]]);

            $lines = [];
            foreach ($record->records('items') as $item) {
                $voucherItemId = $this->optional($context, $item, 'voucher_item_key', VouchersImporter::ITEM_ENTITY, $record->key());
                if ($voucherItemId === null) {
                    continue;
                }

                if (! DB::table(VouchersImporter::ITEM_TABLE)->where('id', $voucherItemId)->where('voucher_id', $voucherId)->exists()) {
                    $context->warn($record->key(), ImportProblem::DanglingReference);

                    continue;
                }

                $lines[$voucherItemId] = [
                    'barangay_id' => $context->barangayId(),
                    'liquidation_id' => $id,
                    'voucher_item_id' => $voucherItemId,
                    'actual_amount' => $item->money('actual_amount'),
                    'created_at' => $context->timestamp,
                    'updated_at' => $context->timestamp,
                ];
            }
            TableWriter::insert(self::ITEM_TABLE, array_values($lines));

            $this->remember($context, $record, self::TABLE, $id);
        }
    }
}
