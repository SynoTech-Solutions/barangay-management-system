<?php

namespace App\Http\Requests\Finance;

use App\Models\BankAccount;
use Illuminate\Foundation\Http\FormRequest;

class BankAccountRequest extends FormRequest
{
    use NormalizesText;

    private const array TEXT_FIELDS = ['account_number', 'account_name', 'depository_bank', 'branch_address'];

    private const int NUMBER_MAX = 64;

    private const int NAME_MAX = 150;

    private const int ADDRESS_MAX = 255;

    protected function prepareForValidation(): void
    {
        $this->normalizeText(self::TEXT_FIELDS);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $account = $this->route('bankAccount');

        return [
            'account_number' => ['required', 'string', 'max:'.self::NUMBER_MAX, $this->uniqueIgnoringCase(BankAccount::class, 'account_number', $account instanceof BankAccount ? $account->id : null)],
            'account_name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'depository_bank' => ['required', 'string', 'max:'.self::NAME_MAX],
            'branch_address' => ['nullable', 'string', 'max:'.self::ADDRESS_MAX],
        ];
    }

    /**
     * @return array{account_number: string, account_name: string, depository_bank: string, branch_address: string|null}
     */
    public function details(): array
    {
        return [
            'account_number' => $this->string('account_number')->toString(),
            'account_name' => $this->string('account_name')->toString(),
            'depository_bank' => $this->string('depository_bank')->toString(),
            'branch_address' => $this->optionalText('branch_address'),
        ];
    }
}
