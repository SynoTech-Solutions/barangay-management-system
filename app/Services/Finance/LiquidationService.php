<?php

namespace App\Services\Finance;

use App\Domain\Finance\LiquidationSettlement;
use App\Domain\Money\Centavos;
use App\Enums\AuditAction;
use App\Enums\DocumentKind;
use App\Enums\ErrorCode;
use App\Enums\FeatureFlag;
use App\Enums\LiquidationStatus;
use App\Enums\PaymentType;
use App\Enums\VoucherStatus;
use App\Exceptions\BmsException;
use App\Models\Budget;
use App\Models\Liquidation;
use App\Models\LiquidationItem;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherItem;
use App\Services\Audit\AuditTrail;
use App\Services\Features\FeatureFlags;
use App\Services\Numbering\DocumentNumberAllocator;
use App\Services\Receipts\OfficialReceiptRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class LiquidationService
{
    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private AuditTrail $audit,
        private DocumentNumberAllocator $numbers,
        private OfficialReceiptRegistry $receipts,
        private FundGuard $funds,
        private VoucherService $vouchers,
        private FeatureFlags $features,
    ) {}

    /**
     * @param  array{liquidated_on: string, or_number: string|null, or_date: string|null}  $details
     * @param  array<int, string>  $actuals
     */
    public function liquidate(Voucher $voucher, array $details, array $actuals, User $actor): Liquidation
    {
        return DB::transaction(function () use ($voucher, $details, $actuals, $actor): Liquidation {
            $locked = $this->vouchers->lock($voucher);
            if ($locked->status !== VoucherStatus::Released || $locked->payment_type !== PaymentType::CashAdvance || $locked->liquidation()->exists()) {
                throw BmsException::of(ErrorCode::VoucherNotUnliquidated, ['number' => $locked->voucher_number]);
            }

            $liquidation = new Liquidation(['voucher_id' => $locked->id]);
            $liquidation->liquidation_number = $this->numbers->next(DocumentKind::LiquidationReport, (int) Budget::query()->whereKey($locked->budget_id)->value('fiscal_year'));
            $liquidation->status = LiquidationStatus::ForClearing;
            $liquidation->recorded_by = $actor->id;
            $liquidation->recorded_at = CarbonImmutable::now();
            $this->apply($liquidation, $locked, $details, $actuals);

            $before = ['status' => $locked->status->value];
            $locked->status = VoucherStatus::Closed;
            $locked->closed_at = CarbonImmutable::now();
            $locked->save();

            $this->audit->record(AuditAction::LiquidationSaved, $liquidation, null, $this->snapshot($liquidation, $locked), $liquidation->barangay_id, $actor->id);
            $this->audit->record(AuditAction::VoucherUpdated, $locked, $before, ['status' => $locked->status->value, 'liquidation_number' => $liquidation->liquidation_number], $locked->barangay_id, $actor->id);

            if (! $this->features->enabled(FeatureFlag::LiquidationClearing)) {
                $this->markCleared($liquidation, $locked, $actor);
            }

            return $liquidation;
        });
    }

    /**
     * @param  array{liquidated_on: string, or_number: string|null, or_date: string|null}  $details
     * @param  array<int, string>  $actuals
     */
    public function update(Liquidation $liquidation, array $details, array $actuals, User $actor): Liquidation
    {
        return DB::transaction(function () use ($liquidation, $details, $actuals, $actor): Liquidation {
            $locked = $this->lockForClearing($liquidation);
            $voucher = Voucher::query()->findOrFail($locked->voucher_id);
            $before = $this->snapshot($locked, $voucher);

            $locked->updated_by = $actor->id;
            $this->apply($locked, $voucher, $details, $actuals);

            $this->audit->record(AuditAction::LiquidationSaved, $locked, $before, $this->snapshot($locked, $voucher), $locked->barangay_id, $actor->id);

            return $locked;
        });
    }

    public function clear(Liquidation $liquidation, User $actor): Liquidation
    {
        return DB::transaction(function () use ($liquidation, $actor): Liquidation {
            $locked = $this->lockForClearing($liquidation);
            $this->markCleared($locked, Voucher::query()->findOrFail($locked->voucher_id), $actor);

            return $locked;
        });
    }

    public function recordPrint(Liquidation $liquidation, User $actor): Liquidation
    {
        return DB::transaction(function () use ($liquidation, $actor): Liquidation {
            $locked = Liquidation::query()->lockForUpdate()->findOrFail($liquidation->id);
            $locked->print_count++;
            $locked->last_printed_at = CarbonImmutable::now();
            $locked->save();

            $this->audit->record(AuditAction::LiquidationPrinted, $locked, after: ['liquidation_number' => $locked->liquidation_number, 'print_count' => $locked->print_count], barangayId: $locked->barangay_id, actorId: $actor->id);

            return $locked;
        });
    }

    public function settlement(Liquidation $liquidation): LiquidationSettlement
    {
        $advanced = 0;
        $actual = 0;
        foreach ($liquidation->items()->with('voucherItem:id,amount')->get() as $item) {
            $advanced += Centavos::fromDecimal($item->voucherItem->amount ?? '0');
            $actual += Centavos::fromDecimal($item->actual_amount);
        }

        return new LiquidationSettlement($advanced, $actual);
    }

    /**
     * @param  array{liquidated_on: string, or_number: string|null, or_date: string|null}  $details
     * @param  array<int, string>  $actuals
     */
    private function apply(Liquidation $liquidation, Voucher $voucher, array $details, array $actuals): void
    {
        $items = $voucher->items()->get(['id', 'amount']);
        $itemIds = $items->pluck('id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
        $given = array_map(intval(...), array_keys($actuals));
        sort($given);
        if ($itemIds !== $given) {
            throw BmsException::of(ErrorCode::LiquidationItemsMismatch, ['number' => $voucher->voucher_number]);
        }

        $settlement = new LiquidationSettlement(
            array_sum($items->map(fn (VoucherItem $item): int => Centavos::fromDecimal($item->amount))->all()),
            array_sum(array_map(Centavos::fromDecimal(...), $actuals)),
        );
        if ($settlement->needsReceipt() && ($details['or_number'] === null || $details['or_date'] === null)) {
            throw BmsException::of(ErrorCode::LiquidationNeedsReceipt, ['amount' => Centavos::toDecimal($settlement->refund())]);
        }

        $liquidation->fill([
            'liquidated_on' => $details['liquidated_on'],
            'or_number' => $settlement->needsReceipt() ? $details['or_number'] : null,
            'or_date' => $settlement->needsReceipt() ? $details['or_date'] : null,
        ]);
        $liquidation->save();

        foreach ($actuals as $voucherItemId => $amount) {
            LiquidationItem::query()->updateOrCreate(
                ['liquidation_id' => $liquidation->id, 'voucher_item_id' => $voucherItemId],
                ['actual_amount' => $amount],
            );
        }

        $this->receipts->sync($liquidation);
    }

    private function markCleared(Liquidation $liquidation, Voucher $voucher, User $actor): void
    {
        $budget = $this->funds->lock($voucher->budget_id);
        $this->funds->ensureCovered($budget, $this->increases($liquidation));

        $liquidation->status = LiquidationStatus::Cleared;
        $liquidation->cleared_by = $actor->id;
        $liquidation->cleared_at = CarbonImmutable::now();
        $liquidation->save();

        $settlement = $this->settlement($liquidation);
        $this->audit->record(AuditAction::LiquidationCleared, $liquidation, ['status' => LiquidationStatus::ForClearing->value], [
            'status' => $liquidation->status->value,
            'liquidation_number' => $liquidation->liquidation_number,
            'refund' => Centavos::toDecimal($settlement->refund()),
            'reimbursement' => Centavos::toDecimal($settlement->reimbursement()),
        ], $liquidation->barangay_id, $actor->id);
    }

    /**
     * @return array<int, int>
     */
    private function increases(Liquidation $liquidation): array
    {
        $deltas = [];
        foreach ($liquidation->items()->with('voucherItem:id,account_id,amount')->get() as $item) {
            $voucherItem = $item->voucherItem;
            if ($voucherItem === null) {
                continue;
            }
            $deltas[$voucherItem->account_id] = ($deltas[$voucherItem->account_id] ?? 0)
                + Centavos::fromDecimal($item->actual_amount) - Centavos::fromDecimal($voucherItem->amount);
        }

        return array_filter($deltas, fn (int $delta): bool => $delta > 0);
    }

    private function lockForClearing(Liquidation $liquidation): Liquidation
    {
        $locked = Liquidation::query()->lockForUpdate()->findOrFail($liquidation->id);
        if ($locked->isCleared()) {
            throw BmsException::of(ErrorCode::LiquidationCleared, ['number' => $locked->liquidation_number]);
        }

        return $locked;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Liquidation $liquidation, Voucher $voucher): array
    {
        $settlement = $this->settlement($liquidation);

        return [
            'liquidation_number' => $liquidation->liquidation_number,
            'voucher_number' => $voucher->voucher_number,
            'liquidated_on' => $liquidation->liquidated_on->format(self::DATE_FORMAT),
            'or_number' => $liquidation->or_number,
            'actual' => Centavos::toDecimal($settlement->actual),
            'refund' => Centavos::toDecimal($settlement->refund()),
            'reimbursement' => Centavos::toDecimal($settlement->reimbursement()),
        ];
    }
}
