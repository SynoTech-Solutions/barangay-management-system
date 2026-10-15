<?php

namespace App\Http\Requests\Documents;

use App\Tenancy\CurrentBarangay;

trait RequiresCancelReason
{
    private const int REASON_MAX = 255;

    /**
     * @return array<string, mixed>
     */
    public function rules(CurrentBarangay $currentBarangay): array
    {
        return [
            ...$this->batchRules($currentBarangay),
            'reason' => ['required', 'string', 'max:'.self::REASON_MAX],
        ];
    }

    public function reason(): string
    {
        return $this->string('reason')->trim()->toString();
    }
}
