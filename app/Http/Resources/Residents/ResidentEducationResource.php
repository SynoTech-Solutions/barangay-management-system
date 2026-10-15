<?php

namespace App\Http\Resources\Residents;

use App\Models\ResidentEducation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ResidentEducation
 */
class ResidentEducationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_id' => $this->school_id,
            'school' => $this->whenLoaded('school', fn () => $this->school->label),
            'course_id' => $this->course_id,
            'course' => $this->whenLoaded('course', fn () => $this->course->label),
            'started_on' => $this->started_on?->toDateString(),
            'ended_on' => $this->ended_on?->toDateString(),
        ];
    }
}
