<?php

namespace App\Http\Resources\Finance;

use App\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Budget
 */
class BudgetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fiscal_year' => $this->fiscal_year,
            'title' => $this->title,
            'status' => $this->status->value,
            'status_label' => __($this->status->labelKey()),
            'is_draft' => $this->isDraft(),
            'approved_by' => $this->relationLoaded('approver') ? $this->approver?->name : null,
            'approved_at' => $this->approved_at?->toIso8601String(),
        ];
    }
}
