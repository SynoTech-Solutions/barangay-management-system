<?php

namespace App\Http\Requests\Finance;

use App\Models\CollectionType;
use App\Models\OtherCollection;
use App\Rules\ActiveResident;
use App\Rules\AvailableOrNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class OtherCollectionRequest extends FormRequest
{
    use NormalizesText;

    public const string AMOUNT_MAX = '9999999999.99';

    private const string AMOUNT_MIN = '0.01';

    private const array TEXT_FIELDS = ['payer_name', 'or_number', 'remarks'];

    private const int NAME_MAX = 255;

    private const int OR_MAX = 32;

    private const int REMARKS_MAX = 255;

    protected function prepareForValidation(): void
    {
        $this->normalizeText(self::TEXT_FIELDS);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $collection = $this->currentCollection();

        return [
            'collection_type_id' => ['required', 'integer', $this->usableType($collection?->collection_type_id)],
            'resident_id' => ['nullable', 'required_without:payer_name', new ActiveResident],
            'payer_name' => ['nullable', 'required_without:resident_id', 'prohibits:resident_id', 'string', 'max:'.self::NAME_MAX],
            'or_number' => ['required', 'string', 'max:'.self::OR_MAX, new AvailableOrNumber($collection)],
            'amount' => ['required', 'decimal:0,2', 'min:'.self::AMOUNT_MIN, 'max:'.self::AMOUNT_MAX],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:'.self::REMARKS_MAX],
        ];
    }

    /**
     * @return array{collection_type_id: int, resident_id: int|null, payer_name: string|null, or_number: string, amount: string, paid_on: string, remarks: string|null}
     */
    public function details(): array
    {
        return [
            'collection_type_id' => $this->integer('collection_type_id'),
            'resident_id' => $this->filled('resident_id') ? $this->integer('resident_id') : null,
            'payer_name' => $this->optionalText('payer_name'),
            'or_number' => $this->string('or_number')->toString(),
            'amount' => $this->string('amount')->toString(),
            'paid_on' => $this->string('paid_on')->toString(),
            'remarks' => $this->optionalText('remarks'),
        ];
    }

    private function currentCollection(): ?OtherCollection
    {
        $collection = $this->route('collection');

        return $collection instanceof OtherCollection ? $collection : null;
    }

    private function usableType(?int $currentId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($currentId): void {
            $usable = is_numeric($value) && CollectionType::query()
                ->whereKey((int) $value)
                ->where(fn ($query) => $query->where('is_active', true)->when($currentId !== null, fn ($inner) => $inner->orWhere('id', $currentId)))
                ->exists();

            if (! $usable) {
                $fail('validation.exists')->translate();
            }
        };
    }
}
