<?php

namespace App\Services\Finance;

use App\Domain\Audit\AuditRedactor;
use App\Domain\Money\Centavos;
use App\Enums\AuditAction;
use App\Enums\CheckStatus;
use App\Enums\ErrorCode;
use App\Enums\VoucherStatus;
use App\Exceptions\BmsException;
use App\Models\BankAccount;
use App\Models\Check;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Voucher;
use App\Services\Audit\AuditSnapshot;
use App\Services\Audit\AuditTrail;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class CheckService
{
    private const array AUDITED_ATTRIBUTES = ['status', 'payee_name', ...Check::DETAIL_ATTRIBUTES];

    public function __construct(
        private AuditTrail $audit,
        private VoucherService $vouchers,
    ) {}

    /**
     * @param  array{bank_account_id: int, check_number: string, check_date: string, remarks: string|null}  $details
     */
    public function issueForVoucher(Voucher $voucher, array $details, User $actor): Check
    {
        return DB::transaction(function () use ($voucher, $details, $actor): Check {
            $locked = $this->vouchers->lock($voucher);
            if ($locked->status !== VoucherStatus::Approved || ! $this->vouchers->requiresCheck($locked)) {
                throw BmsException::of(ErrorCode::VoucherWrongStatus, ['number' => $locked->voucher_number]);
            }
            $existing = $locked->activeCheck()->first();
            if ($existing !== null) {
                throw BmsException::of(ErrorCode::VoucherHasCheck, ['number' => $locked->voucher_number, 'check' => $existing->check_number]);
            }

            $check = new Check([
                ...$details,
                'voucher_id' => $locked->id,
                'vendor_id' => $locked->vendor_id,
                'payee_name' => $locked->payee_name,
                'amount' => Centavos::toDecimal($this->vouchers->total($locked)),
            ]);

            return $this->insert($check, $actor);
        });
    }

    /**
     * @param  array{vendor_id: int, amount: string, bank_account_id: int, check_number: string, check_date: string, remarks: string|null}  $details
     */
    public function issueDirect(array $details, User $actor): Check
    {
        return DB::transaction(function () use ($details, $actor): Check {
            $vendor = Vendor::query()->findOrFail($details['vendor_id']);
            $check = new Check([...$details, 'voucher_id' => null, 'payee_name' => $vendor->name]);

            return $this->insert($check, $actor);
        });
    }

    /**
     * @param  array{bank_account_id: int, check_number: string, check_date: string, remarks: string|null, vendor_id?: int, amount?: string}  $details
     */
    public function update(Check $check, array $details, User $actor): Check
    {
        return DB::transaction(function () use ($check, $details, $actor): Check {
            $locked = $this->lockForRelease($check);
            $before = AuditSnapshot::of($locked, self::AUDITED_ATTRIBUTES);

            if ($locked->isDirect()) {
                $vendor = Vendor::query()->findOrFail($details['vendor_id'] ?? $locked->vendor_id);
                $locked->fill([...$details, 'payee_name' => $vendor->name]);
            } else {
                unset($details['vendor_id'], $details['amount']);
                $locked->fill($details);
            }
            $this->ensureBankAccount($locked->bank_account_id);
            $locked->updated_by = $actor->id;
            $this->saveUnique($locked);

            [$changedBefore, $changedAfter] = AuditRedactor::changedOnly($before, AuditSnapshot::of($locked, self::AUDITED_ATTRIBUTES));
            $this->audit->record(AuditAction::CheckUpdated, $locked, $changedBefore, $changedAfter, $locked->barangay_id, $actor->id);

            return $locked;
        });
    }

    public function cancel(Check $check, string $reason, User $actor): Check
    {
        return DB::transaction(function () use ($check, $reason, $actor): Check {
            $locked = $this->lockForRelease($check);
            $locked->status = CheckStatus::Cancelled;
            $locked->cancelled_by = $actor->id;
            $locked->cancelled_at = CarbonImmutable::now();
            $locked->cancel_reason = $reason;
            $locked->save();

            $this->audit->record(AuditAction::CheckCancelled, $locked, ['status' => CheckStatus::ForRelease->value], ['status' => $locked->status->value, 'check_number' => $locked->check_number, 'reason' => $reason], $locked->barangay_id, $actor->id);

            return $locked;
        });
    }

    public function release(Check $check, string $receivedBy, User $actor): Check
    {
        return DB::transaction(function () use ($check, $receivedBy, $actor): Check {
            $voucher = $check->voucher_id === null ? null : $this->vouchers->lock(Voucher::query()->findOrFail($check->voucher_id));
            $locked = $this->lockForRelease($check);

            $locked->status = CheckStatus::Released;
            $locked->released_by = $actor->id;
            $locked->released_at = CarbonImmutable::now();
            $locked->received_by_name = $receivedBy;
            $locked->save();

            $this->audit->record(AuditAction::CheckReleased, $locked, ['status' => CheckStatus::ForRelease->value], ['status' => $locked->status->value, 'check_number' => $locked->check_number, 'received_by' => $receivedBy], $locked->barangay_id, $actor->id);

            if ($voucher !== null) {
                $this->vouchers->markPaid($voucher, $actor);
            }

            return $locked;
        });
    }

    public function recordPrint(Check $check, User $actor): Check
    {
        return DB::transaction(function () use ($check, $actor): Check {
            $locked = Check::query()->lockForUpdate()->findOrFail($check->id);
            if ($locked->status === CheckStatus::Cancelled) {
                throw BmsException::of(ErrorCode::CheckCancelled, ['number' => $locked->check_number]);
            }

            $locked->print_count++;
            $locked->last_printed_at = CarbonImmutable::now();
            $locked->save();

            $this->audit->record(AuditAction::CheckPrinted, $locked, after: ['check_number' => $locked->check_number, 'print_count' => $locked->print_count], barangayId: $locked->barangay_id, actorId: $actor->id);

            return $locked;
        });
    }

    private function insert(Check $check, User $actor): Check
    {
        $this->ensureBankAccount($check->bank_account_id);
        $check->status = CheckStatus::ForRelease;
        $check->issued_by = $actor->id;
        $check->issued_at = CarbonImmutable::now();
        $check->updated_by = $actor->id;
        $this->saveUnique($check);

        $this->audit->record(AuditAction::CheckIssued, $check, null, AuditSnapshot::of($check, ['voucher_id', ...self::AUDITED_ATTRIBUTES]), $check->barangay_id, $actor->id);

        return $check;
    }

    private function saveUnique(Check $check): void
    {
        try {
            DB::transaction(fn () => $check->save());
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), Check::NUMBER_INDEX)) {
                throw BmsException::of(ErrorCode::CheckNumberTaken, ['number' => $check->check_number]);
            }

            throw $exception;
        }
    }

    private function ensureBankAccount(int $bankAccountId): void
    {
        BankAccount::query()->findOrFail($bankAccountId);
    }

    private function lockForRelease(Check $check): Check
    {
        $locked = Check::query()->lockForUpdate()->findOrFail($check->id);
        if ($locked->status !== CheckStatus::ForRelease) {
            throw BmsException::of(ErrorCode::CheckNotForRelease, ['number' => $locked->check_number]);
        }

        return $locked;
    }
}
