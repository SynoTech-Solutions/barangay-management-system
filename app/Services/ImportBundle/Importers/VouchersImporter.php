<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Enums\DocumentKind;
use App\Enums\PaymentType;
use App\Enums\VoucherStatus;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\Record;
use App\Services\ImportBundle\TableWriter;

final class VouchersImporter extends BaseImporter
{
    public const string TABLE = 'vouchers';

    public const string ITEM_TABLE = 'voucher_items';

    public const string ITEM_ENTITY = 'voucher_items';

    private const array VERIFIED = [VoucherStatus::Verified, VoucherStatus::Approved, VoucherStatus::Released, VoucherStatus::Closed];

    private const array APPROVED = [VoucherStatus::Approved, VoucherStatus::Released, VoucherStatus::Closed];

    private const array RELEASED = [VoucherStatus::Released, VoucherStatus::Closed];

    private const array TEXTS = [
        'payee_address', 'payee_tin', 'responsibility_center', 'office_unit', 'treasurer_name',
        'bookkeeper_name', 'punong_barangay_name', 'head_of_appropriations_name',
    ];

    public function entity(): BundleEntity
    {
        return BundleEntity::Vouchers;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        foreach ($records as $record) {
            $date = (string) $record->raw('voucher_date');
            $year = (int) substr($date, 0, 4);
            if ($this->alreadyImported($context, $record)) {
                $this->observe($context, $record, 'source_number', DocumentKind::DisbursementVoucher, $year);

                continue;
            }

            $budgetId = $this->required($context, $record, 'budget_key', BundleEntity::Budgets);
            if ($budgetId === null) {
                continue;
            }

            $items = $record->records('items');
            $accounts = [];
            foreach ($items as $item) {
                $accounts[] = $this->required($context, $item, 'account_key', BundleEntity::Accounts, $record->key());
            }
            if (in_array(null, $accounts, true)) {
                continue;
            }

            $status = VoucherStatus::from($record->requiredText('status'));
            $preparedAt = $context->utcOrNow($record->raw('prepared_at'));
            $verifiedAt = $context->utc($record->raw('verified_at')) ?? (in_array($status, self::VERIFIED, true) ? $preparedAt : null);
            $approvedAt = $context->utc($record->raw('approved_at')) ?? (in_array($status, self::APPROVED, true) ? $verifiedAt : null);
            $releasedAt = $context->utc($record->raw('released_at')) ?? (in_array($status, self::RELEASED, true) ? $approvedAt : null);
            [$number, $sourceNumber] = $this->number($context, $record, 'source_number', self::TABLE, 'voucher_number', DocumentKind::DisbursementVoucher, $year);

            $id = TableWriter::reserveId(self::TABLE);
            $row = [
                'id' => $id,
                'barangay_id' => $context->barangayId(),
                'voucher_number' => $number,
                'source_number' => $sourceNumber,
                'import_note' => $this->note($record),
                'budget_id' => $budgetId,
                'payment_type' => PaymentType::from($record->requiredText('payment_type'))->value,
                'status' => $status->value,
                'voucher_date' => $date,
                'vendor_id' => $this->optional($context, $record, 'vendor_key', BundleEntity::Vendors),
                'resident_id' => $this->optional($context, $record, 'resident_key', BundleEntity::Residents),
                'payee_name' => $record->requiredText('payee_name'),
                'particulars' => $record->requiredText('particulars'),
                'prepared_at' => $preparedAt,
                'verified_at' => $verifiedAt,
                'approved_at' => $approvedAt,
                'released_at' => $releasedAt,
                'closed_at' => $status === VoucherStatus::Closed ? ($context->utc($record->raw('closed_at')) ?? $releasedAt) : null,
                'cancelled_at' => $status === VoucherStatus::Cancelled ? ($context->utc($record->raw('cancelled_at')) ?? $preparedAt) : null,
                'cancel_reason' => $status === VoucherStatus::Cancelled ? $this->cancelReason($record) : null,
                'created_at' => $preparedAt,
                'updated_at' => $context->timestamp,
            ];
            foreach (self::TEXTS as $field) {
                $row[$field] = $record->text($field);
            }
            TableWriter::insert(self::TABLE, [$row]);

            $this->items($context, $id, $items, $accounts);
            $this->remember($context, $record, self::TABLE, $id);
        }
    }

    /**
     * @param  list<Record>  $items
     * @param  list<int|null>  $accounts
     */
    private function items(ImportContext $context, int $voucherId, array $items, array $accounts): void
    {
        $ids = TableWriter::reserveIds(self::ITEM_TABLE, count($items));
        $rows = [];
        foreach ($items as $index => $item) {
            $rows[] = [
                'id' => $ids[$index],
                'barangay_id' => $context->barangayId(),
                'voucher_id' => $voucherId,
                'account_id' => $accounts[$index],
                'description' => $item->requiredText('description'),
                'amount' => $item->money('amount'),
                'position' => $index + 1,
                'created_at' => $context->timestamp,
                'updated_at' => $context->timestamp,
            ];
            $context->ids->remember(self::ITEM_ENTITY, $item->key(), self::ITEM_TABLE, $ids[$index]);
        }

        TableWriter::insert(self::ITEM_TABLE, $rows);
    }
}
