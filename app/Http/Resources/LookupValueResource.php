<?php

namespace App\Http\Resources;

use App\Models\LookupValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LookupValue
 */
class LookupValueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'is_active' => $this->is_active,
            'is_global' => $this->isGlobal(),
        ];
    }
}
