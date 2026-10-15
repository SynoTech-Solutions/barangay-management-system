<?php

namespace App\Http\Resources\Cedula;

use App\Models\CedulaTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CedulaTemplate
 */
class CedulaTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_exempt' => $this->is_exempt,
            'business_gross_receipts' => $this->business_gross_receipts,
            'salaries' => $this->salaries,
            'real_property_income' => $this->real_property_income,
            'is_active' => $this->is_active,
        ];
    }
}
