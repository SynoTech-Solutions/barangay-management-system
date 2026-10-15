<?php

namespace App\Http\Requests\Finance;

use App\Enums\Permission;
use App\Models\BankAccount;
use App\Models\Check;
use App\Models\Vendor;
use App\Rules\ActiveOption;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class CheckRequest extends FormRequest
{
    use NormalizesText;

    public const string AMOUNT_MIN = '1.00';

    private const array TEXT_FIELDS = ['check_number', 'remarks'];

    private const int NUMBER_MAX = 32;

    private const int REMARKS_MAX = 255;

    public function authorize(): bool
    {
        return ! $this->isDirect() || $this->user()?->can(Permission::ChecksIssueDirect->value) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeText(self::TEXT_FIELDS);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $check = $this->currentCheck();
        $rules = [
            'bank_account_id' => ['required', 'integer', new ActiveOption(BankAccount::class, $check?->bank_account_id)],
            'check_number' => ['required', 'string', 'max:'.self::NUMBER_MAX, $this->availableNumber($check?->id)],
            'check_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:'.self::REMARKS_MAX],
        ];

        if ($this->isDirect()) {
            $rules['vendor_id'] = ['required', 'integer', new ActiveOption(Vendor::class, $check?->vendor_id)];
            $rules['amount'] = ['required', 'decimal:0,2', 'min:'.self::AMOUNT_MIN, 'max:'.OtherCollectionRequest::AMOUNT_MAX];
        }

        return $rules;
    }

    /**
     * @return array{bank_account_id: int, check_number: string, check_date: string, remarks: string|null}
     */
    public function details(): array
    {
        return [
            'bank_account_id' => $this->integer('bank_account_id'),
            'check_number' => $this->string('check_number')->toString(),
            'check_date' => $this->string('check_date')->toString(),
            'remarks' => $this->optionalText('remarks'),
        ];
    }

    /**
     * @return array{vendor_id: int, amount: string, bank_account_id: int, check_number: string, check_date: string, remarks: string|null}
     */
    public function directDetails(): array
    {
        return [
            ...$this->details(),
            'vendor_id' => $this->integer('vendor_id'),
            'amount' => $this->string('amount')->toString(),
        ];
    }

    public function isDirect(): bool
    {
        $check = $this->currentCheck();

        return $check !== null ? $check->isDirect() : $this->route('voucher') === null;
    }

    private function currentCheck(): ?Check
    {
        $check = $this->route('check');

        return $check instanceof Check ? $check : null;
    }

    private function availableNumber(?int $exceptId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($exceptId): void {
            $taken = is_string($value) && is_numeric($this->input('bank_account_id')) && Check::query()
                ->where('bank_account_id', (int) $this->input('bank_account_id'))
                ->where('check_number', $value)
                ->whereNull('cancelled_at')
                ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
                ->exists();

            if ($taken) {
                $fail('finance.validation.check_number_taken')->translate();
            }
        };
    }
}
