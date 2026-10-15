<?php

namespace App\Http\Requests\Sms;

use App\Tenancy\CurrentBarangay;
use Illuminate\Validation\Rule;

class ResidentSmsGroupRequest extends ResidentSmsSelectionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'sms_group_id' => ['required', 'integer', Rule::exists('sms_groups', 'id')->where('barangay_id', app(CurrentBarangay::class)->require()->id)],
            'confirmed_count' => ['required', 'integer', 'min:1'],
        ];
    }

    public function groupId(): int
    {
        return $this->integer('sms_group_id');
    }

    public function confirmedCount(): int
    {
        return $this->integer('confirmed_count');
    }
}
