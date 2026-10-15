<?php

namespace App\Http\Resources\Businesses;

use App\Domain\Residents\AddressComposer;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Business
 */
class BusinessOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_number' => $this->business_number,
            'name' => $this->name,
            'owner_name' => $this->ownerDisplayName(),
            'address' => AddressComposer::compose([$this->street_address, $this->purok?->name]),
            'is_active' => $this->isActive(),
        ];
    }
}
