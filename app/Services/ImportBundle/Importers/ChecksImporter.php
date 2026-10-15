<?php

namespace App\Services\ImportBundle\Importers;

use App\Domain\Money\Centavos;
use App\Enums\BundleEntity;
use App\Enums\CheckStatus;
use App\Enums\ImportProblem;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\TableWriter;

final class ChecksImporter extends BaseImporter
{
    public const string TABLE = 'checks';

    private const int MINIMUM_CENTAVOS = 100;

    public function entity(): BundleEntity
    {
        return BundleEntity::Checks;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        foreach ($records as $record) {
            if ($this->alreadyImported($context, $record)) {
                continue;
            }

            $bankId = $this->required($context, $record, 'bank_account_key', BundleEntity::BankAccounts);
            if ($bankId === null) {
                continue;
            }

            $voucherId = null;
            if ($record->ref('voucher_key') !== null) {
                $voucherId = $this->required($context, $record, 'voucher_key', BundleEntity::Vouchers);
                if ($voucherId === null) {
                    continue;
                }
            }

            $amount = $record->money('amount');
            if (Centavos::fromDecimal($amount) < self::MINIMUM_CENTAVOS) {
                $context->failed($record->key(), ImportProblem::AmountBelowMinimum);

                continue;
            }

            $status = CheckStatus::from($record->requiredText('status'));
            $checkNumber = $record->requiredText('check_number');
            if ($status !== CheckStatus::Cancelled && (! $context->unique->claim(self::TABLE, 'check_number', $checkNumber, scope: [['bank_account_id', '=', $bankId], ['cancelled_at', '=', null]])
                || ($voucherId !== null && ! $context->unique->claim(self::TABLE, 'voucher_id', (string) $voucherId, scope: [['cancelled_at', '=', null]])))) {
                $context->failed($record->key(), ImportProblem::CheckConflict);

                continue;
            }

            $issuedAt = $context->utcOrNow($record->raw('issued_at'));
            $released = $status === CheckStatus::Released;
            $id = TableWriter::reserveId(self::TABLE);
            TableWriter::insert(self::TABLE, [[
                'id' => $id,
                'barangay_id' => $context->barangayId(),
                'voucher_id' => $voucherId,
                'bank_account_id' => $bankId,
                'vendor_id' => $this->optional($context, $record, 'vendor_key', BundleEntity::Vendors),
                'payee_name' => $record->requiredText('payee_name'),
                'check_number' => $checkNumber,
                'check_date' => $record->raw('check_date') ?? $context->local($record->raw('issued_at'))->toDateString(),
                'amount' => $amount,
                'remarks' => $record->text('remarks'),
                'status' => $status->value,
                'issued_at' => $issuedAt,
                'released_at' => $released ? ($context->utc($record->raw('released_at')) ?? $issuedAt) : null,
                'received_by_name' => $released ? $record->text('received_by_name') : null,
                'cancelled_at' => $status === CheckStatus::Cancelled ? ($context->utc($record->raw('cancelled_at')) ?? $issuedAt) : null,
                'cancel_reason' => $status === CheckStatus::Cancelled ? $this->cancelReason($record) : null,
                'created_at' => $issuedAt,
                'updated_at' => $context->timestamp,
            ]]);

            $this->remember($context, $record, self::TABLE, $id);
        }
    }
}
