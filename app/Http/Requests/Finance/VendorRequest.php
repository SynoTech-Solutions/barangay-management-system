<?php

namespace App\Http\Requests\Finance;

use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;

class VendorRequest extends FormRequest
{
    use NormalizesText;

    private const array TEXT_FIELDS = ['name', 'address', 'contact_number', 'email', 'contact_person', 'position', 'tin'];

    private const int NAME_MAX = 150;

    private const int ADDRESS_MAX = 255;

    private const int CONTACT_MAX = 50;

    private const int POSITION_MAX = 100;

    private const int TIN_MAX = 32;

    protected function prepareForValidation(): void
    {
        $this->normalizeText(self::TEXT_FIELDS);
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower($this->input('email'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $vendor = $this->route('vendor');

        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, $this->uniqueIgnoringCase(Vendor::class, 'name', $vendor instanceof Vendor ? $vendor->id : null)],
            'address' => ['required', 'string', 'max:'.self::ADDRESS_MAX],
            'contact_number' => ['required', 'string', 'max:'.self::CONTACT_MAX],
            'email' => ['nullable', 'email', 'max:'.self::NAME_MAX],
            'contact_person' => ['nullable', 'string', 'max:'.self::NAME_MAX],
            'position' => ['nullable', 'string', 'max:'.self::POSITION_MAX],
            'tin' => ['nullable', 'string', 'max:'.self::TIN_MAX],
        ];
    }

    /**
     * @return array{name: string, address: string, contact_number: string, email: string|null, contact_person: string|null, position: string|null, tin: string|null}
     */
    public function details(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'address' => $this->string('address')->toString(),
            'contact_number' => $this->string('contact_number')->toString(),
            'email' => $this->optionalText('email'),
            'contact_person' => $this->optionalText('contact_person'),
            'position' => $this->optionalText('position'),
            'tin' => $this->optionalText('tin'),
        ];
    }
}
