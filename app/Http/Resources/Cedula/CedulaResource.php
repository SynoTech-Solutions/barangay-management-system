<?php

namespace App\Http\Resources\Cedula;

use App\Http\Resources\Documents\PresentsIssuedDocument;
use App\Models\Cedula;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cedula
 */
class CedulaResource extends JsonResource
{
    use PresentsIssuedDocument;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cedula_number' => $this->cedula_number,
            'form_number' => $this->form_number,
            'tax_year' => $this->tax_year,
            'place_of_issue' => $this->place_of_issue,
            'resident' => $this->whenLoaded('resident', fn () => $this->residentSummary($this->resident)),
            'is_exempt' => $this->is_exempt,
            'business_gross_receipts' => $this->business_gross_receipts,
            'salaries' => $this->salaries,
            'real_property_income' => $this->real_property_income,
            'basic_tax' => $this->basic_tax,
            'additional_tax' => $this->additional_tax,
            'interest' => $this->interest,
            'breakdown' => $this->breakdown,
            ...$this->lifecycle($this->resource),
        ];
    }
}
