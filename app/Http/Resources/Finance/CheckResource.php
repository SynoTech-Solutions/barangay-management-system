<?php

namespace App\Http\Resources\Finance;

use App\Models\Check;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Check
 */
class CheckResource extends JsonResource
{
    private const string DATE_FORMAT = 'Y-m-d';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $bank = $this->relationLoaded('bankAccount') ? $this->bankAccount : null;

        return [
            'id' => $this->id,
            'voucher_id' => $this->voucher_id,
            'voucher_number' => $this->relationLoaded('voucher') ? $this->voucher?->voucher_number : null,
            'is_direct' => $this->isDirect(),
            'vendor_id' => $this->vendor_id,
            'payee_name' => $this->payee_name,
            'bank_account_id' => $this->bank_account_id,
            'bank_account' => $bank === null ? null : "{$bank->depository_bank} · {$bank->account_number}",
            'check_number' => $this->check_number,
            'check_date' => $this->check_date->format(self::DATE_FORMAT),
            'amount' => $this->amount,
            'remarks' => $this->remarks,
            'status' => $this->status->value,
            'status_label' => __($this->status->labelKey()),
            'issued_by' => $this->relationLoaded('issuer') ? $this->issuer?->name : null,
            'issued_at' => $this->issued_at->toIso8601String(),
            'released_by' => $this->relationLoaded('releaser') ? $this->releaser?->name : null,
            'released_at' => $this->released_at?->toIso8601String(),
            'received_by_name' => $this->received_by_name,
            'cancelled_by' => $this->relationLoaded('canceller') ? $this->canceller?->name : null,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'print_count' => $this->print_count,
        ];
    }
}
