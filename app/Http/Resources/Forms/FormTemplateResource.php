<?php

namespace App\Http\Resources\Forms;

use App\Models\FormTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FormTemplate
 */
class FormTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'template_number' => $this->template_number,
            'name' => $this->name,
            'type_id' => $this->type_id,
            'type' => $this->whenLoaded('type', fn () => $this->type?->label),
            'kind' => $this->kind->value,
            'kind_label' => __($this->kind->labelKey()),
            'is_active' => $this->is_active,
            'version' => $this->whenLoaded('currentVersion', fn () => $this->currentVersion?->version),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
