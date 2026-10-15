<?php

namespace App\Http\Resources\Residents;

use App\Models\Purok;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Purok
 */
class PurokResource extends JsonResource
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
            'residents_count' => $this->residents_count ?? 0,
        ];
    }
}
