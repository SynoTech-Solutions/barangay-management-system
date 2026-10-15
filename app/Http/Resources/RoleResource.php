<?php

namespace App\Http\Resources;

use App\Enums\SystemRole;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $system = SystemRole::tryFrom($this->key);

        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $system !== null && $this->is_system ? __($system->labelKey()) : ($this->name ?? $this->key),
            'scope' => $this->scope->value,
            'is_system' => $this->is_system,
        ];
    }
}
