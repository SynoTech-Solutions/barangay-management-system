<?php

namespace App\Http\Requests\Finance;

use App\Enums\BudgetStatus;
use App\Enums\PaymentType;
use App\Models\Budget;
use App\Models\Vendor;
use App\Models\Voucher;
use App\Rules\ActiveOption;
use App\Rules\ActiveResident;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VoucherRequest extends FormRequest
{
    use NormalizesText;

    public const int ITEMS_MAX = 50;

    private const string AMOUNT_MIN = '0.01';

    private const array TEXT_FIELDS = ['payee_name', 'particulars', 'responsibility_center', 'office_unit'];

    private const int NAME_MAX = 255;

    private const int PARTICULARS_MAX = 500;

    private const int UNIT_MAX = 150;

    private const int DESCRIPTION_MAX = 255;

    protected function prepareForValidation(): void
    {
        $this->normalizeText(self::TEXT_FIELDS);
        $voucher = $this->currentVoucher();
        if ($voucher !== null) {
            $this->merge(['budget_id' => $voucher->budget_id]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $voucher = $this->currentVoucher();

        return [
            'budget_id' => ['required', 'integer', $this->approvedBudget()],
            'payment_type' => ['required', Rule::enum(PaymentType::class)],
            'voucher_date' => ['required', 'date'],
            'vendor_id' => ['nullable', 'prohibits:resident_id,payee_name', new ActiveOption(Vendor::class, $voucher?->vendor_id)],
            'resident_id' => ['nullable', 'prohibits:payee_name', new ActiveResident],
            'payee_name' => ['nullable', 'required_without_all:vendor_id,resident_id', 'string', 'max:'.self::NAME_MAX],
            'particulars' => ['required', 'string', 'max:'.self::PARTICULARS_MAX],
            'responsibility_center' => ['nullable', 'string', 'max:'.self::UNIT_MAX],
            'office_unit' => ['nullable', 'string', 'max:'.self::UNIT_MAX],
            'items' => ['required', 'array', 'min:1', 'max:'.self::ITEMS_MAX],
            'items.*.account_id' => ['required', 'integer'],
            'items.*.description' => ['required', 'string', 'max:'.self::DESCRIPTION_MAX],
            'items.*.amount' => ['required', 'decimal:0,2', 'min:'.self::AMOUNT_MIN, 'max:'.OtherCollectionRequest::AMOUNT_MAX],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['budget_id', 'voucher_date'])) {
                return;
            }

            $year = Budget::query()->whereKey($this->integer('budget_id'))->value('fiscal_year');
            if (CarbonImmutable::parse($this->string('voucher_date')->toString())->year !== $year) {
                $validator->errors()->add('voucher_date', (string) __('finance.validation.voucher_date_in_budget_year', ['year' => $year]));
            }
        }];
    }

    /**
     * @return array{budget_id: int, payment_type: PaymentType, voucher_date: string, vendor_id: int|null, resident_id: int|null, payee_name: string|null, particulars: string, responsibility_center: string|null, office_unit: string|null}
     */
    public function details(): array
    {
        return [
            'budget_id' => $this->integer('budget_id'),
            'payment_type' => PaymentType::from($this->string('payment_type')->toString()),
            'voucher_date' => $this->string('voucher_date')->toString(),
            'vendor_id' => $this->filled('vendor_id') ? $this->integer('vendor_id') : null,
            'resident_id' => $this->filled('resident_id') ? $this->integer('resident_id') : null,
            'payee_name' => $this->optionalText('payee_name'),
            'particulars' => $this->string('particulars')->toString(),
            'responsibility_center' => $this->optionalText('responsibility_center'),
            'office_unit' => $this->optionalText('office_unit'),
        ];
    }

    /**
     * @return list<array{account_id: int, description: string, amount: string}>
     */
    public function lines(): array
    {
        $items = [];
        foreach ((array) $this->input('items', []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $items[] = [
                'account_id' => (int) ($item['account_id'] ?? 0),
                'description' => trim((string) ($item['description'] ?? '')),
                'amount' => (string) ($item['amount'] ?? '0'),
            ];
        }

        return $items;
    }

    private function approvedBudget(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $approved = is_numeric($value) && Budget::query()->whereKey((int) $value)->where('status', BudgetStatus::Approved->value)->exists();
            if (! $approved) {
                $fail('validation.exists')->translate();
            }
        };
    }

    private function currentVoucher(): ?Voucher
    {
        $voucher = $this->route('voucher');

        return $voucher instanceof Voucher ? $voucher : null;
    }
}
