<?php

namespace App\Http\Resources\Cedula;

use App\Models\Cedula;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cedula
 */
class CedulaReferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cedula_number' => $this->cedula_number,
            'tax_year' => $this->tax_year,
            'ctc_number' => $this->referenceNumber(),
            'ctc_place' => $this->place_of_issue,
            'ctc_date' => $this->issued_at->toDateString(),
        ];
    }
}
