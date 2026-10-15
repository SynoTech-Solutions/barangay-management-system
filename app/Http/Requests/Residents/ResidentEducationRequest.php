<?php

namespace App\Http\Requests\Residents;

use App\Enums\LookupType;
use App\Http\Requests\Residents\Concerns\ValidatesDateRange;
use App\Models\ResidentEducation;
use App\Rules\LookupOption;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ResidentEducationRequest extends FormRequest
{
    use ValidatesDateRange;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->route('education');
        $current = $current instanceof ResidentEducation ? $current : null;

        return [
            'school_id' => ['required', new LookupOption(LookupType::School, $current?->school_id)],
            'course_id' => ['required', new LookupOption(LookupType::Course, $current?->course_id)],
            'started_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->ensureRangeOrder($validator)];
    }

    /**
     * @return array{school_id: int, course_id: int, started_on: string|null, ended_on: string|null}
     */
    public function education(): array
    {
        return [
            'school_id' => $this->integer('school_id'),
            'course_id' => $this->integer('course_id'),
            'started_on' => $this->optionalDate('started_on'),
            'ended_on' => $this->optionalDate('ended_on'),
        ];
    }
}
