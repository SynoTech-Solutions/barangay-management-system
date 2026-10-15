<?php

namespace App\Services\Finance;

use App\Domain\Finance\ConsumptionEntry;
use App\Domain\Finance\DisbursementRecord;
use App\Domain\Money\Centavos;
use App\Enums\CheckStatus;
use App\Enums\LiquidationStatus;
use App\Enums\VoucherStatus;
use App\Models\Account;
use App\Models\Budget;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

final readonly class VoucherFundConsumption implements FundConsumption
{
    public function entriesByAccount(Budget $budget): array
    {
        $rows = $this->items($budget)
            ->whereNotNull('v.approved_at')
            ->addSelect(['i.account_id', 'i.amount', 'v.status'])
            ->get();

        $entries = [];
        foreach ($rows as $row) {
            $entries[(int) $row->account_id][] = new ConsumptionEntry(
                Centavos::fromDecimal((string) $row->amount),
                $row->actual_amount === null ? null : Centavos::fromDecimal((string) $row->actual_amount),
                $row->status === VoucherStatus::Cancelled->value,
            );
        }

        return $entries;
    }

    public function disbursements(Budget $budget, Account $account): array
    {
        $rows = $this->items($budget)
            ->where('i.account_id', $account->id)
            ->whereIn('v.status', [VoucherStatus::Released->value, VoucherStatus::Closed->value])
            ->leftJoin('checks as c', fn (JoinClause $join) => $join->on('c.voucher_id', '=', 'v.id')->whereNull('c.cancelled_at')->where('c.status', CheckStatus::Released->value))
            ->orderBy('v.voucher_date')
            ->orderBy('v.voucher_number')
            ->orderBy('i.position')
            ->addSelect(['v.voucher_number', 'v.voucher_date', 'v.payee_name', 'i.description', 'i.amount', 'c.check_number', 'l.liquidation_number'])
            ->get();

        $records = [];
        foreach ($rows as $row) {
            $amount = $row->actual_amount ?? $row->amount;
            $records[] = new DisbursementRecord(
                (string) $row->voucher_number,
                (string) $row->voucher_date,
                (string) $row->payee_name,
                (string) $row->description,
                Centavos::fromDecimal((string) $amount),
                $row->check_number === null ? null : (string) $row->check_number,
                $row->liquidation_number === null ? null : (string) $row->liquidation_number,
            );
        }

        return $records;
    }

    private function items(Budget $budget): Builder
    {
        return DB::table('voucher_items as i')
            ->join('vouchers as v', 'v.id', '=', 'i.voucher_id')
            ->leftJoin('liquidations as l', 'l.voucher_id', '=', 'v.id')
            ->leftJoin('liquidation_items as li', 'li.voucher_item_id', '=', 'i.id')
            ->selectRaw('case when l.status = ? then li.actual_amount end as actual_amount', [LiquidationStatus::Cleared->value])
            ->where('v.barangay_id', $budget->barangay_id)
            ->where('i.barangay_id', $budget->barangay_id)
            ->where('v.budget_id', $budget->id);
    }
}
