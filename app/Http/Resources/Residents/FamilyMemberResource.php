<?php

namespace App\Http\Resources\Residents;

use App\Domain\Residents\ResidentAge;
use App\Models\FamilyMember;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FamilyMember
 */
class FamilyMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $related = $this->relationLoaded('relatedResident') ? $this->relatedResident : null;
        $birthdate = $related !== null ? $related->birthdate : $this->birthdate;

        return [
            'id' => $this->id,
            'relationship_id' => $this->relationship_id,
            'relationship' => $this->whenLoaded('relationship', fn () => $this->relationship->label),
            'full_name' => $related !== null ? $related->full_name : $this->full_name,
            'birthdate' => $birthdate?->toDateString(),
            'age' => ResidentAge::years($birthdate, CarbonImmutable::today()),
            'related_resident' => $related === null ? null : (new ResidentOptionResource($related))->resolve(),
        ];
    }
}
