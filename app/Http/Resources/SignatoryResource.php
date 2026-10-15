<?php

namespace App\Http\Resources;

use App\Enums\SignatoryOffice;
use App\Models\Signatory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{office: SignatoryOffice, signatory: Signatory|null} $resource
 */
class SignatoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $office = $this->resource['office'];
        $signatory = $this->resource['signatory'];

        return [
            'office' => $office->value,
            'office_label' => __($office->labelKey()),
            'name' => $signatory?->name,
            'title' => $signatory?->title,
            'has_signature' => $signatory?->signature_path !== null,
        ];
    }
}
