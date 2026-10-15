<?php

namespace App\Http\Resources\Finance;

use App\Models\Account;
use App\Queries\ChartOfAccountsQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Account
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'kind' => $this->kind->value,
            'kind_label' => __($this->kind->labelKey()),
            'group_type' => $this->group_type?->value,
            'group_type_label' => $this->group_type === null ? null : __($this->group_type->labelKey()),
            'code' => $this->code,
            'name' => $this->name,
            'level' => $this->level,
            'remarks' => $this->remarks,
            'is_locked' => $this->is_locked,
            'is_active' => $this->is_active,
            'is_posting' => ChartOfAccountsQuery::isPosting($this->resource),
            'children_count' => $this->children_count ?? 0,
        ];
    }
}
