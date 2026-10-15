<?php

namespace App\Http\Requests\Businesses;

use App\Enums\BusinessStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessStatusRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(BusinessStatus::class)]];
    }

    public function status(): BusinessStatus
    {
        return BusinessStatus::from($this->string('status')->toString());
    }
}
