<?php

namespace App\Http\Resources;

use App\Models\Barangay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Barangay
 */
class BarangayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'psgc_code' => $this->psgc_code,
            'status' => $this->status->value,
            'municipality' => $this->whenLoaded('municipality', fn () => $this->municipality->name),
        ];
    }
}
