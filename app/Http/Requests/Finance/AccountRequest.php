<?php

namespace App\Http\Requests\Finance;

use App\Domain\Residents\NameNormalizer;
use App\Enums\AccountGroupType;
use App\Enums\AccountKind;
use App\Models\Account;
use App\Services\Finance\ChartOfAccounts;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
    private const array TEXT_FIELDS = ['name', 'remarks', 'code', 'series'];

    private const int CODE_MAX = 32;

    private const int SERIES_MAX = 8;

    private const int NAME_MAX = 200;

    private const int REMARKS_MAX = 255;

    private const string CODE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9.\-]*$/';

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
        $kind = AccountKind::tryFrom($this->string('kind')->toString());

        return [
            'kind' => ['required', Rule::enum(AccountKind::class)],
            'code' => [Rule::requiredIf($kind !== AccountKind::Subsidiary), 'nullable', 'string', 'max:'.self::CODE_MAX, 'regex:'.self::CODE_PATTERN, $this->availableCode()],
            'series' => [Rule::requiredIf($kind === AccountKind::Subsidiary), 'nullable', 'string', 'max:'.self::SERIES_MAX, 'regex:'.self::CODE_PATTERN, $this->availableCode()],
            'name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'remarks' => ['nullable', 'string', 'max:'.self::REMARKS_MAX],
            'parent_id' => [Rule::requiredIf($kind !== null && $kind !== AccountKind::Group), 'nullable', 'integer'],
            'group_type' => [Rule::requiredIf($kind === AccountKind::Group), 'nullable', Rule::enum(AccountGroupType::class)],
        ];
    }

    /**
     * @return array{kind: AccountKind, code: string|null, series: string|null, name: string, remarks: string|null, parent_id: int|null, group_type: AccountGroupType|null}
     */
    public function details(): array
    {
        $kind = AccountKind::from($this->string('kind')->toString());

        return [
            'kind' => $kind,
            'code' => $kind === AccountKind::Subsidiary ? null : $this->string('code')->toString(),
            'series' => $kind === AccountKind::Subsidiary ? $this->string('series')->toString() : null,
            'name' => $this->string('name')->toString(),
            'remarks' => $this->filled('remarks') ? $this->string('remarks')->toString() : null,
            'parent_id' => $kind === AccountKind::Group ? null : $this->integer('parent_id'),
            'group_type' => $kind === AccountKind::Group ? AccountGroupType::from($this->string('group_type')->toString()) : null,
        ];
    }

    private function availableCode(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            $code = $value;
            if ($attribute === 'series') {
                $parentCode = Account::query()->whereKey($this->integer('parent_id'))->value('code');
                if (! is_string($parentCode)) {
                    return;
                }
                $code = ChartOfAccounts::subsidiaryCode($parentCode, $value);
            }

            if (Account::query()->whereRaw('lower(code) = lower(?)', [$code])->exists()) {
                $fail('validation.unique')->translate();
            }
        };
    }
}
