<?php

namespace App\Http\Resources\Finance;

use App\Models\Voucher;
use App\Models\VoucherItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Voucher
 */
class VoucherResource extends JsonResource
{
    private const string DATE_FORMAT = 'Y-m-d';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $check = $this->relationLoaded('activeCheck') ? $this->activeCheck : null;

        return [
            'id' => $this->id,
            'voucher_number' => $this->voucher_number,
            'budget_id' => $this->budget_id,
            'payment_type' => $this->payment_type->value,
            'payment_type_label' => __($this->payment_type->labelKey()),
            'status' => $this->status->value,
            'status_label' => __($this->status->labelKey()),
            'voucher_date' => $this->voucher_date->format(self::DATE_FORMAT),
            'vendor_id' => $this->vendor_id,
            'resident_id' => $this->resident_id,
            'payee_name' => $this->payee_name,
            'payee_address' => $this->payee_address,
            'payee_tin' => $this->payee_tin,
            'particulars' => $this->particulars,
            'responsibility_center' => $this->responsibility_center,
            'office_unit' => $this->office_unit,
            'total' => $this->items_total,
            'items' => $this->relationLoaded('items') ? array_values($this->items->map(fn (VoucherItem $item): array => [
                'id' => $item->id,
                'account_id' => $item->account_id,
                'account' => $item->relationLoaded('account') && $item->account !== null ? "{$item->account->code} · {$item->account->name}" : null,
                'description' => $item->description,
                'amount' => $item->amount,
            ])->all()) : [],
            'check' => $check === null ? null : ['id' => $check->id, 'check_number' => $check->check_number, 'status' => $check->status->value, 'status_label' => __($check->status->labelKey())],
            'liquidation_number' => $this->relationLoaded('liquidation') ? $this->liquidation?->liquidation_number : null,
            'prepared_by' => $this->relationLoaded('preparer') ? $this->preparer?->name : null,
            'prepared_at' => $this->prepared_at->toIso8601String(),
            'verified_by' => $this->relationLoaded('verifier') ? $this->verifier?->name : null,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'approved_by' => $this->relationLoaded('approver') ? $this->approver?->name : null,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'released_at' => $this->released_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'cancelled_by' => $this->relationLoaded('canceller') ? $this->canceller?->name : null,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'print_count' => $this->print_count,
        ];
    }
}
