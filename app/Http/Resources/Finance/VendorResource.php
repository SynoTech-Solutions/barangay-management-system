<?php

namespace App\Http\Resources\Finance;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vendor
 */
class VendorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'contact_number' => $this->contact_number,
            'email' => $this->email,
            'contact_person' => $this->contact_person,
            'position' => $this->position,
            'tin' => $this->tin,
            'is_active' => $this->is_active,
        ];
    }
}
