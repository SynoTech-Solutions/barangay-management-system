<?php

namespace App\Http\Requests\Businesses;

use App\Domain\Residents\NameNormalizer;
use App\Enums\LookupType;
use App\Models\Business;
use App\Rules\ActiveResident;
use App\Rules\LookupOption;
use App\Rules\PurokOption;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class BusinessRequest extends FormRequest
{
    public const string AMOUNT_MAX = '9999999999.99';

    private const int NAME_MAX = 150;

    private const int TEXT_MAX = 255;

    private const int PLATE_MAX = 32;

    private const array TEXT_FIELDS = ['name', 'owner_name', 'street_address', 'plate_number'];

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (self::TEXT_FIELDS as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = NameNormalizer::normalize($this->input($field));
            }
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $business = $this->business();

        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, $this->uniqueName($business)],
            'owner_resident_id' => ['nullable', 'required_without:owner_name', new ActiveResident],
            'owner_name' => ['nullable', 'required_without:owner_resident_id', 'prohibits:owner_resident_id', 'string', 'max:'.self::TEXT_MAX],
            'category_id' => ['required', new LookupOption(LookupType::BusinessCategory, $business?->category_id)],
            'nature_id' => ['required', new LookupOption(LookupType::BusinessNature, $business?->nature_id)],
            'ownership_type_id' => ['required', new LookupOption(LookupType::BusinessOwnershipType, $business?->ownership_type_id)],
            'purok_id' => ['nullable', new PurokOption($business?->purok_id)],
            'street_address' => ['nullable', 'string', 'max:'.self::TEXT_MAX],
            'plate_number' => ['nullable', 'string', 'max:'.self::PLATE_MAX],
            'operating_since' => ['nullable', 'date', 'before_or_equal:today'],
            'capitalization' => ['nullable', 'decimal:0,2', 'min:0', 'max:'.self::AMOUNT_MAX],
            'gross_receipts' => ['nullable', 'decimal:0,2', 'min:0', 'max:'.self::AMOUNT_MAX],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'owner_resident_id' => $this->filled('owner_resident_id') ? $this->integer('owner_resident_id') : null,
            'owner_name' => $this->optional('owner_name'),
            'category_id' => $this->integer('category_id'),
            'nature_id' => $this->integer('nature_id'),
            'ownership_type_id' => $this->integer('ownership_type_id'),
            'purok_id' => $this->filled('purok_id') ? $this->integer('purok_id') : null,
            'street_address' => $this->optional('street_address'),
            'plate_number' => $this->optional('plate_number'),
            'operating_since' => $this->optional('operating_since'),
            'capitalization' => $this->optional('capitalization'),
            'gross_receipts' => $this->optional('gross_receipts'),
        ];
    }

    private function business(): ?Business
    {
        $business = $this->route('business');

        return $business instanceof Business ? $business : null;
    }

    private function uniqueName(?Business $business): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($business): void {
            $taken = is_string($value) && Business::query()
                ->whereRaw('lower(name) = lower(?)', [$value])
                ->when($business !== null, fn ($query) => $query->whereKeyNot($business?->id))
                ->exists();

            if ($taken) {
                $fail('businesses.validation.name_taken')->translate(['name' => $value]);
            }
        };
    }

    private function optional(string $field): ?string
    {
        return $this->filled($field) ? $this->string($field)->toString() : null;
    }
}
