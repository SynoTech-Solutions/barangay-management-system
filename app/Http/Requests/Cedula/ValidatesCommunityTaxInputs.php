<?php

namespace App\Http\Requests\Cedula;

trait ValidatesCommunityTaxInputs
{
    public const string INCOME_MAX = '9999999999.99';

    public const array INCOME_FIELDS = ['business_gross_receipts', 'salaries', 'real_property_income'];

    /**
     * @return array<string, mixed>
     */
    protected function incomeRules(): array
    {
        $rules = ['is_exempt' => ['nullable', 'boolean']];
        foreach (self::INCOME_FIELDS as $field) {
            $rules[$field] = ['nullable', 'decimal:0,2', 'min:0', 'max:'.self::INCOME_MAX];
        }

        return $rules;
    }

    /**
     * @return array{is_exempt: bool, business_gross_receipts: string, salaries: string, real_property_income: string}
     */
    public function inputs(): array
    {
        return [
            'is_exempt' => $this->boolean('is_exempt'),
            'business_gross_receipts' => $this->amount('business_gross_receipts'),
            'salaries' => $this->amount('salaries'),
            'real_property_income' => $this->amount('real_property_income'),
        ];
    }

    private function amount(string $field): string
    {
        return $this->filled($field) ? $this->string($field)->toString() : '0';
    }
}
