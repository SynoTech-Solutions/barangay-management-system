<?php

namespace App\Http\Requests\Residents\Concerns;

use App\Domain\Residents\NameNormalizer;
use App\Queries\ResidentDuplicateQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * @mixin FormRequest
 */
trait WarnsAboutDuplicates
{
    public const string DUPLICATE_FIELD = 'duplicate';

    protected function warnAboutDuplicate(Validator $validator): void
    {
        if ($this->boolean('confirm_duplicate') || $validator->errors()->hasAny(['last_name', 'first_name', 'birthdate'])) {
            return;
        }

        $lastName = NameNormalizer::normalize($this->string('last_name')->toString());
        $firstName = NameNormalizer::normalize($this->string('first_name')->toString());

        if ($lastName === null || $firstName === null) {
            return;
        }

        $existing = app(ResidentDuplicateQuery::class)->find(
            $lastName,
            $firstName,
            $this->filled('birthdate') ? $this->string('birthdate')->toString() : null,
        );

        if ($existing !== null) {
            $validator->errors()->add(self::DUPLICATE_FIELD, (string) __('residents.validation.duplicate', [
                'name' => $existing->full_name,
                'number' => $existing->resident_number,
            ]));
        }
    }
}
