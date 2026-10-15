<?php

namespace App\Http\Requests\Businesses;

use App\Enums\BusinessDimension;
use App\Enums\BusinessStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessReportRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dimension' => ['nullable', Rule::enum(BusinessDimension::class)],
            'status' => ['nullable', Rule::enum(BusinessStatus::class)],
        ];
    }

    public function dimension(): BusinessDimension
    {
        return BusinessDimension::tryFrom($this->string('dimension')->toString()) ?? BusinessDimension::Category;
    }

    public function status(): ?BusinessStatus
    {
        return BusinessStatus::tryFrom($this->string('status')->toString());
    }
}
