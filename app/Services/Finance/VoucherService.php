<?php

namespace App\Services\Finance;

use App\Domain\Audit\AuditRedactor;
use App\Domain\Money\Centavos;
use App\Enums\AccountGroupType;
use App\Enums\AccountKind;
use App\Enums\AuditAction;
use App\Enums\CheckStatus;
use App\Enums\DocumentKind;
use App\Enums\ErrorCode;
use App\Enums\FeatureFlag;
use App\Enums\PaymentType;
use App\Enums\SignatoryOffice;
use App\Enums\VoucherStatus;
use App\Exceptions\BmsException;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Resident;
use App\Models\Signatory;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Voucher;
use App\Models\VoucherItem;
use App\Services\Audit\AuditSnapshot;
use App\Services\Audit\AuditTrail;
use App\Services\Features\FeatureFlags;
use App\Services\Numbering\DocumentNumberAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class VoucherService
{
    private const array AUDITED_ATTRIBUTES = ['voucher_number', 'status', ...Voucher::DETAIL_ATTRIBUTES];

    public function __construct(
        private AuditTrail $audit,
        private DocumentNumberAllocator $numbers,
        private FundGuard $funds,
        private FeatureFlags $features,
    ) {}

    /**
     * @param  array{budget_id: int, payment_type: PaymentType, voucher_date: string, vendor_id: int|null, resident_id: int|null, payee_name: string|null, particulars: string, responsibility_center: string|null, office_unit: string|null}  $details
     * @param  list<array{account_id: int, description: string, amount: string}>  $items
     */
    public function create(array $details, array $items, User $actor): Voucher
    {
        return DB::transaction(function () use ($details, $items, $actor): Voucher {
            $budget = $this->approvedBudget($details['budget_id']);
            $this->ensureFundSources(array_column($items, 'account_id'));

            $voucher = new Voucher;
            $this->fill($voucher, $details);
            $this->captureSignatories($voucher);
            $voucher->voucher_number = $this->numbers->next(DocumentKind::DisbursementVoucher, $budget->fiscal_year);
            $voucher->status = VoucherStatus::Prepared;
            $voucher->prepared_by = $actor->id;
            $voucher->prepared_at = CarbonImmutable::now();
            $voucher->updated_by = $actor->id;
            $voucher->save();
            $this->replaceItems($voucher, $items);

            $this->audit->record(AuditAction::VoucherPrepared, $voucher, null, $this->snapshot($voucher), $voucher->barangay_id, $actor->id);

            return $voucher;
        });
    }

    /**
     * @param  array{budget_id: int, payment_type: PaymentType, voucher_date: string, vendor_id: int|null, resident_id: int|null, payee_name: string|null, particulars: string, responsibility_center: string|null, office_unit: string|null}  $details
     * @param  list<array{account_id: int, description: string, amount: string}>  $items
     */
    public function update(Voucher $voucher, array $details, array $items, User $actor): Voucher
    {
        return DB::transaction(function () use ($voucher, $details, $items, $actor): Voucher {
            $locked = $this->lock($voucher);
            if (! $locked->status->isEditable()) {
                throw BmsException::of(ErrorCode::VoucherNotEditable, ['number' => $locked->voucher_number]);
            }

            $this->approvedBudget($locked->budget_id);
            $this->ensureFundSources(array_column($items, 'account_id'));

            $before = $this->snapshot($locked);
            $this->fill($locked, [...$details, 'budget_id' => $locked->budget_id]);
            $this->captureSignatories($locked);
            $locked->status = VoucherStatus::Prepared;
            $locked->verified_by = null;
            $locked->verified_at = null;
            $locked->updated_by = $actor->id;
            $locked->save();
            $this->replaceItems($locked, $items);

            [$changedBefore, $changedAfter] = AuditRedactor::changedOnly($before, $this->snapshot($locked));
            $this->audit->record(AuditAction::VoucherUpdated, $locked, $changedBefore, $changedAfter, $locked->barangay_id, $actor->id);

            return $locked;
        });
    }

    public function verify(Voucher $voucher, User $actor): Voucher
    {
        return DB::transaction(function () use ($voucher, $actor): Voucher {
            $locked = $this->lockInStatus($voucher, VoucherStatus::Prepared);
            $locked->status = VoucherStatus::Verified;
            $locked->verified_by = $actor->id;
            $locked->verified_at = CarbonImmutable::now();
            $locked->save();

            $this->audit->record(AuditAction::VoucherVerified, $locked, ['status' => VoucherStatus::Prepared->value], ['status' => $locked->status->value], $locked->barangay_id, $actor->id);

            return $locked;
        });
    }

    public function approve(Voucher $voucher, User $actor): Voucher
    {
        return DB::transaction(function () use ($voucher, $actor): Voucher {
            $locked = $this->lockInStatus($voucher, VoucherStatus::Verified);
            $budget = $this->funds->lock($locked->budget_id);
            if (! $budget->isApproved()) {
                throw BmsException::of(ErrorCode::BudgetNotApproved, ['year' => $budget->fiscal_year]);
            }
            $this->funds->ensureCovered($budget, $this->amountsByAccount($locked));

            $now = CarbonImmutable::now();
            $this->captureSignatories($locked);
            $locked->status = VoucherStatus::Approved;
            $locked->approved_by = $actor->id;
            $locked->approved_at = $now;

            $autoReleased = ! $this->requiresCheck($locked);
            if ($autoReleased) {
                $locked->status = VoucherStatus::Closed;
                $locked->released_at = $now;
                $locked->closed_at = $now;
            }

            $locked->save();

            $this->audit->record(AuditAction::VoucherApproved, $locked, ['status' => VoucherStatus::Verified->value], ['status' => VoucherStatus::Approved->value, 'total' => Centavos::toDecimal($this->total($locked))], $locked->barangay_id, $actor->id);
            if ($autoReleased) {
                $this->audit->record(AuditAction::VoucherReleased, $locked, ['status' => VoucherStatus::Approved->value], ['status' => $locked->status->value], $locked->barangay_id, $actor->id);
            }

            return $locked;
        });
    }

    public function cancel(Voucher $voucher, string $reason, User $actor): Voucher
    {
        return DB::transaction(function () use ($voucher, $reason, $actor): Voucher {
            $locked = $this->lock($voucher);
            if ($locked->status === VoucherStatus::Cancelled) {
                throw BmsException::of(ErrorCode::VoucherWrongStatus, ['number' => $locked->voucher_number]);
            }
            if ($locked->liquidation()->exists()) {
                throw BmsException::of(ErrorCode::VoucherLiquidated, ['number' => $locked->voucher_number]);
            }

            $check = $locked->activeCheck()->lockForUpdate()->first();
            if ($check !== null && $check->status === CheckStatus::Released) {
                throw BmsException::of(ErrorCode::VoucherHasReleasedCheck, ['number' => $locked->voucher_number, 'check' => $check->check_number]);
            }
            if (in_array($locked->status, [VoucherStatus::Released, VoucherStatus::Closed], true)) {
                throw BmsException::of(ErrorCode::VoucherPaidWithoutCheck, ['number' => $locked->voucher_number]);
            }

            $now = CarbonImmutable::now();
            if ($check !== null) {
                $check->status = CheckStatus::Cancelled;
                $check->cancelled_by = $actor->id;
                $check->cancelled_at = $now;
                $check->cancel_reason = $reason;
                $check->save();
                $this->audit->record(AuditAction::CheckCancelled, $check, ['status' => CheckStatus::ForRelease->value], ['status' => $check->status->value, 'check_number' => $check->check_number, 'reason' => $reason], $check->barangay_id, $actor->id);
            }

            $before = ['status' => $locked->status->value];
            $locked->status = VoucherStatus::Cancelled;
            $locked->cancelled_by = $actor->id;
            $locked->cancelled_at = $now;
            $locked->cancel_reason = $reason;
            $locked->save();

            $this->audit->record(AuditAction::VoucherCancelled, $locked, $before, ['status' => $locked->status->value, 'reason' => $reason, 'voided_check' => $check?->check_number], $locked->barangay_id, $actor->id);

            return $locked;
        });
    }

    public function markPaid(Voucher $locked, User $actor): void
    {
        $now = CarbonImmutable::now();
        $before = ['status' => $locked->status->value];
        $awaitsLiquidation = $locked->isCashAdvance() && $this->features->enabled(FeatureFlag::CashAdvanceLiquidation);
        $locked->released_at = $now;
        $locked->status = $awaitsLiquidation ? VoucherStatus::Released : VoucherStatus::Closed;
        $locked->closed_at = $awaitsLiquidation ? null : $now;
        $locked->save();

        $this->audit->record(AuditAction::VoucherReleased, $locked, $before, ['status' => $locked->status->value], $locked->barangay_id, $actor->id);
    }

    public function recordPrint(Voucher $voucher, User $actor): Voucher
    {
        return DB::transaction(function () use ($voucher, $actor): Voucher {
            $locked = $this->lock($voucher);
            if ($locked->status === VoucherStatus::Cancelled) {
                throw BmsException::of(ErrorCode::VoucherWrongStatus, ['number' => $locked->voucher_number]);
            }
            if ($this->features->enabled(FeatureFlag::DvPrintRequiresCheck) && $this->requiresCheck($locked) && ! $locked->activeCheck()->exists()) {
                throw BmsException::of(ErrorCode::VoucherNeedsCheck, ['number' => $locked->voucher_number]);
            }

            $locked->print_count++;
            $locked->last_printed_at = CarbonImmutable::now();
            $locked->save();

            $this->audit->record(AuditAction::VoucherPrinted, $locked, after: ['voucher_number' => $locked->voucher_number, 'print_count' => $locked->print_count], barangayId: $locked->barangay_id, actorId: $actor->id);

            return $locked;
        });
    }

    public function requiresCheck(Voucher $voucher): bool
    {
        return $voucher->payment_type !== PaymentType::Others || ! $this->features->enabled(FeatureFlag::VoucherOthersAutoRelease);
    }

    /**
     * @return array<int, int>
     */
    public function amountsByAccount(Voucher $voucher): array
    {
        $amounts = [];
        foreach ($voucher->items()->get(['account_id', 'amount']) as $item) {
            $amounts[$item->account_id] = ($amounts[$item->account_id] ?? 0) + Centavos::fromDecimal($item->amount);
        }

        return $amounts;
    }

    public function total(Voucher $voucher): int
    {
        return array_sum($this->amountsByAccount($voucher));
    }

    public function lock(Voucher $voucher): Voucher
    {
        return Voucher::query()->lockForUpdate()->findOrFail($voucher->id);
    }

    private function lockInStatus(Voucher $voucher, VoucherStatus $status): Voucher
    {
        $locked = $this->lock($voucher);
        if ($locked->status !== $status) {
            throw BmsException::of(ErrorCode::VoucherWrongStatus, ['number' => $locked->voucher_number]);
        }

        return $locked;
    }

    private function approvedBudget(int $budgetId): Budget
    {
        $budget = Budget::query()->findOrFail($budgetId);
        if (! $budget->isApproved()) {
            throw BmsException::of(ErrorCode::BudgetNotApproved, ['year' => $budget->fiscal_year]);
        }

        return $budget;
    }

    /**
     * @param  array{budget_id: int, payment_type: PaymentType, voucher_date: string, vendor_id: int|null, resident_id: int|null, payee_name: string|null, particulars: string, responsibility_center: string|null, office_unit: string|null}  $details
     */
    private function fill(Voucher $voucher, array $details): void
    {
        $vendor = $details['vendor_id'] === null ? null : Vendor::query()->findOrFail($details['vendor_id']);
        $resident = $details['resident_id'] === null ? null : Resident::query()->findOrFail($details['resident_id']);

        $voucher->fill([
            ...$details,
            'payee_name' => $vendor->name ?? $resident->full_name ?? (string) $details['payee_name'],
            'payee_address' => $vendor?->address,
            'payee_tin' => $vendor?->tin,
        ]);
    }

    private function captureSignatories(Voucher $voucher): void
    {
        $signatories = Signatory::forOffices(SignatoryOffice::Treasurer, SignatoryOffice::Bookkeeper, SignatoryOffice::PunongBarangay, SignatoryOffice::HeadOfAppropriations);
        $voucher->treasurer_name = $signatories->get(SignatoryOffice::Treasurer->value)?->name;
        $voucher->bookkeeper_name = $signatories->get(SignatoryOffice::Bookkeeper->value)?->name;
        $voucher->punong_barangay_name = $signatories->get(SignatoryOffice::PunongBarangay->value)?->name;
        $voucher->head_of_appropriations_name = $signatories->get(SignatoryOffice::HeadOfAppropriations->value)?->name;
    }

    /**
     * @param  list<array{account_id: int, description: string, amount: string}>  $items
     */
    private function replaceItems(Voucher $voucher, array $items): void
    {
        $voucher->items()->delete();
        foreach ($items as $position => $item) {
            VoucherItem::query()->create([...$item, 'voucher_id' => $voucher->id, 'position' => $position + 1]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Voucher $voucher): array
    {
        $items = array_values($voucher->items()->with('account:id,code')->get()
            ->map(fn (VoucherItem $item): array => ['account' => $item->account?->code, 'description' => $item->description, 'amount' => $item->amount])
            ->all());

        return [...AuditSnapshot::of($voucher, self::AUDITED_ATTRIBUTES), 'items' => $items];
    }

    /**
     * @param  list<int>  $accountIds
     */
    private function ensureFundSources(array $accountIds): void
    {
        $valid = Account::query()
            ->whereIn('id', $accountIds)
            ->where('is_active', true)
            ->where('kind', '<>', AccountKind::Group->value)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('accounts as children')->whereColumn('children.parent_id', 'accounts.id'))
            ->whereHas('group', fn (Builder $group) => $group->where('group_type', '<>', AccountGroupType::Income->value))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $invalid = array_diff($accountIds, $valid);
        if ($invalid !== []) {
            $id = (int) reset($invalid);
            $code = Account::query()->whereKey($id)->value('code');
            throw BmsException::of(ErrorCode::AccountNotFundSource, ['code' => is_string($code) ? $code : (string) $id]);
        }
    }
}
