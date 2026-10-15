<?php

namespace App\Http\Requests\Residents\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * @mixin FormRequest
 */
trait ValidatesDateRange
{
    protected function ensureRangeOrder(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['started_on', 'ended_on']) || ! $this->filled('started_on') || ! $this->filled('ended_on')) {
            return;
        }

        if (Carbon::parse($this->string('ended_on')->toString())->lt(Carbon::parse($this->string('started_on')->toString()))) {
            $validator->errors()->add('ended_on', (string) __('residents.validation.ended_before_started'));
        }
    }

    protected function optionalDate(string $field): ?string
    {
        return $this->filled($field) ? $this->string($field)->toString() : null;
    }
}
