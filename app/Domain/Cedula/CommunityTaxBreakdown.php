<?php

namespace App\Domain\Cedula;

use App\Domain\Money\Centavos;

final readonly class CommunityTaxBreakdown
{
    public const int VERSION = 1;

    public const string RULE = 'lgc-1991-individual';

    public function __construct(
        public IndividualTaxInput $input,
        public int $basic,
        public int $business,
        public int $salary,
        public int $realProperty,
        public int $additional,
        public bool $additionalCapped,
        public int $subtotal,
        public bool $interestApplied,
        public int $interestMonths,
        public int $interestBasisPoints,
        public int $interest,
        public int $total,
    ) {}

    /**
     * @return array{v: int, rule: string, tax_year: int, exempt: bool, inputs: array{business_gross_receipts: string, salaries: string, real_property_income: string}, basic: string, business: string, salary: string, real_property: string, additional: string, additional_capped: bool, subtotal: string, interest_applied: bool, interest_months: int, interest_basis_points: int, interest: string, total: string}
     */
    public function toArray(): array
    {
        return [
            'v' => self::VERSION,
            'rule' => self::RULE,
            'tax_year' => $this->input->taxYear,
            'exempt' => $this->input->exempt,
            'inputs' => [
                'business_gross_receipts' => Centavos::toDecimal($this->input->businessGrossReceipts),
                'salaries' => Centavos::toDecimal($this->input->salaries),
                'real_property_income' => Centavos::toDecimal($this->input->realPropertyIncome),
            ],
            'basic' => Centavos::toDecimal($this->basic),
            'business' => Centavos::toDecimal($this->business),
            'salary' => Centavos::toDecimal($this->salary),
            'real_property' => Centavos::toDecimal($this->realProperty),
            'additional' => Centavos::toDecimal($this->additional),
            'additional_capped' => $this->additionalCapped,
            'subtotal' => Centavos::toDecimal($this->subtotal),
            'interest_applied' => $this->interestApplied,
            'interest_months' => $this->interestMonths,
            'interest_basis_points' => $this->interestBasisPoints,
            'interest' => Centavos::toDecimal($this->interest),
            'total' => Centavos::toDecimal($this->total),
        ];
    }
}
