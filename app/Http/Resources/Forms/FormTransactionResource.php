<?php

namespace App\Http\Resources\Forms;

use App\Http\Resources\Documents\PresentsIssuedDocument;
use App\Models\FormTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FormTransaction
 */
class FormTransactionResource extends JsonResource
{
    use PresentsIssuedDocument;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_number' => $this->transaction_number,
            'form_template_id' => $this->form_template_id,
            'template' => $this->whenLoaded('template', fn () => $this->template?->name),
            'template_kind' => $this->whenLoaded('template', fn () => $this->template?->kind->value),
            'template_type' => $this->whenLoaded('template', fn () => $this->template?->relationLoaded('type') ? $this->template->type?->label : null),
            'resident' => $this->whenLoaded('resident', fn () => $this->residentSummary($this->resident)),
            'or_number' => $this->or_number,
            ...$this->lifecycle($this->resource),
        ];
    }
}
