<?php

namespace App\Http\Resources;

use App\Domain\Features\FlagCatalog;
use App\Domain\Features\FlagOwners;
use App\Domain\Features\FlagState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property FlagState $resource
 */
class FeatureStateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = $this->resource;
        $definition = FlagCatalog::definition($state->flag);

        return [
            'flag' => $state->flag->value,
            'label' => __($state->flag->labelKey()),
            'description' => __($state->flag->descriptionKey()),
            'area' => $definition->area->value,
            'area_label' => __("features.areas.{$definition->area->value}"),
            'kind' => $definition->kind->value,
            'owner' => FlagOwners::for($definition->area),
            'remove_after' => $definition->removeAfter,
            'default_enabled' => $definition->defaultEnabled,
            'requires' => array_map(fn ($flag): string => $flag->value, $definition->requires),
            'enabled' => $state->enabled,
            'source' => $state->source->value,
            'source_label' => __("features.sources.{$state->source->value}"),
            'blocked_by' => $state->blockedBy?->value,
        ];
    }
}
