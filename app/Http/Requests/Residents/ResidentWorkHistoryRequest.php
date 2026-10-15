<?php

namespace App\Http\Requests\Residents;

use App\Enums\LookupType;
use App\Http\Requests\Residents\Concerns\ValidatesDateRange;
use App\Models\ResidentWorkHistory;
use App\Rules\LookupOption;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ResidentWorkHistoryRequest extends FormRequest
{
    use ValidatesDateRange;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->route('workHistory');
        $current = $current instanceof ResidentWorkHistory ? $current : null;

        return [
            'company_id' => ['required', new LookupOption(LookupType::Company, $current?->company_id)],
            'position_id' => ['required', new LookupOption(LookupType::Position, $current?->position_id)],
            'employment_status_id' => ['required', new LookupOption(LookupType::EmploymentStatus, $current?->employment_status_id)],
            'started_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date'],
            'is_current' => ['boolean'],
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
     * @return array{company_id: int, position_id: int, employment_status_id: int, started_on: string|null, ended_on: string|null, is_current: bool}
     */
    public function work(): array
    {
        $current = $this->boolean('is_current');

        return [
            'company_id' => $this->integer('company_id'),
            'position_id' => $this->integer('position_id'),
            'employment_status_id' => $this->integer('employment_status_id'),
            'started_on' => $this->optionalDate('started_on'),
            'ended_on' => $current ? null : $this->optionalDate('ended_on'),
            'is_current' => $current,
        ];
    }
}
