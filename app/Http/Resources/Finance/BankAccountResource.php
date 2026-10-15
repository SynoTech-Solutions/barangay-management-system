<?php

namespace App\Http\Resources\Finance;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankAccount
 */
class BankAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_number' => $this->account_number,
            'account_name' => $this->account_name,
            'depository_bank' => $this->depository_bank,
            'branch_address' => $this->branch_address,
            'is_active' => $this->is_active,
        ];
    }
}
