<?php

namespace App\Http\Requests\Finance;

use App\Models\Liquidation;
use App\Rules\AvailableOrNumber;
use Illuminate\Foundation\Http\FormRequest;

class LiquidationRequest extends FormRequest
{
    use NormalizesText;

    private const string AMOUNT_MIN = '0';

    private const int OR_MAX = 32;

    protected function prepareForValidation(): void
    {
        $this->normalizeText(['or_number']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $liquidation = $this->route('liquidation');

        return [
            'liquidated_on' => ['required', 'date', 'before_or_equal:today'],
            'or_number' => ['nullable', 'string', 'max:'.self::OR_MAX, new AvailableOrNumber($liquidation instanceof Liquidation ? $liquidation : null)],
            'or_date' => ['nullable', 'date', 'before_or_equal:today'],
            'items' => ['required', 'array', 'min:1', 'max:'.VoucherRequest::ITEMS_MAX],
            'items.*.voucher_item_id' => ['required', 'integer', 'distinct'],
            'items.*.actual_amount' => ['required', 'decimal:0,2', 'min:'.self::AMOUNT_MIN, 'max:'.OtherCollectionRequest::AMOUNT_MAX],
        ];
    }

    /**
     * @return array{liquidated_on: string, or_number: string|null, or_date: string|null}
     */
    public function details(): array
    {
        return [
            'liquidated_on' => $this->string('liquidated_on')->toString(),
            'or_number' => $this->optionalText('or_number'),
            'or_date' => $this->filled('or_date') ? $this->string('or_date')->toString() : null,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actuals(): array
    {
        $actuals = [];
        foreach ((array) $this->input('items', []) as $item) {
            if (is_array($item)) {
                $actuals[(int) ($item['voucher_item_id'] ?? 0)] = (string) ($item['actual_amount'] ?? '0');
            }
        }

        return $actuals;
    }
}
