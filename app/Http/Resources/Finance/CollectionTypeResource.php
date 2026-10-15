<?php

namespace App\Http\Resources\Finance;

use App\Models\CollectionType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CollectionType
 */
class CollectionTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'collections_count' => $this->collections_count ?? 0,
        ];
    }
}
