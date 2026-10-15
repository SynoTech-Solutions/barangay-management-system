<?php

namespace App\Http\Resources\Finance;

use App\Domain\Finance\LiquidationSettlement;
use App\Domain\Money\Centavos;
use App\Models\Liquidation;
use App\Models\LiquidationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Liquidation
 */
class LiquidationResource extends JsonResource
{
    private const string DATE_FORMAT = 'Y-m-d';

    private const string ZERO = '0.00';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $voucher = $this->relationLoaded('voucher') ? $this->voucher : null;
        $advanced = 0;
        $actual = 0;
        $items = [];
        if ($this->relationLoaded('items')) {
            foreach ($this->items as $item) {
                $original = $item->voucherItem->amount ?? self::ZERO;
                $advanced += Centavos::fromDecimal($original);
                $actual += Centavos::fromDecimal($item->actual_amount);
                $items[] = $this->item($item, $original);
            }
        }
        $settlement = new LiquidationSettlement($advanced, $actual);

        return [
            'id' => $this->id,
            'liquidation_number' => $this->liquidation_number,
            'voucher_id' => $this->voucher_id,
            'voucher_number' => $voucher?->voucher_number,
            'payee_name' => $voucher?->payee_name,
            'voucher_date' => $voucher?->voucher_date->format(self::DATE_FORMAT),
            'liquidated_on' => $this->liquidated_on->format(self::DATE_FORMAT),
            'or_number' => $this->or_number,
            'or_date' => $this->or_date?->format(self::DATE_FORMAT),
            'status' => $this->status->value,
            'status_label' => __($this->status->labelKey()),
            'advanced' => Centavos::toDecimal($advanced),
            'actual' => Centavos::toDecimal($actual),
            'refund' => Centavos::toDecimal($settlement->refund()),
            'reimbursement' => Centavos::toDecimal($settlement->reimbursement()),
            'items' => $items,
            'cleared_by' => $this->relationLoaded('clearer') ? $this->clearer?->name : null,
            'cleared_at' => $this->cleared_at?->toIso8601String(),
            'print_count' => $this->print_count,
        ];
    }

    /**
     * @return array{voucher_item_id: int, original_amount: string, actual_amount: string}
     */
    private function item(LiquidationItem $item, string $original): array
    {
        return [
            'voucher_item_id' => $item->voucher_item_id,
            'original_amount' => $original,
            'actual_amount' => $item->actual_amount,
        ];
    }
}
