<?php

namespace App\Domain\Numbering;

use App\Enums\DocumentKind;

final readonly class NumberingRule
{
    public function __construct(
        public DocumentKind $kind,
        public string $prefix,
        public bool $resetsYearly,
    ) {}

    public static function for(DocumentKind $kind): self
    {
        return match ($kind) {
            DocumentKind::Resident => new self($kind, 'RES', false),
            DocumentKind::BarangayClearance => new self($kind, 'BC', true),
            DocumentKind::BarangayClearanceControl => new self($kind, 'BCC', true),
            DocumentKind::Business => new self($kind, 'BUS', false),
            DocumentKind::BusinessClearance => new self($kind, 'BUC', true),
            DocumentKind::BusinessClearanceControl => new self($kind, 'BUCC', true),
            DocumentKind::Blotter => new self($kind, 'BLT', true),
            DocumentKind::Cedula => new self($kind, 'CTC', true),
            DocumentKind::IndigencyCertificate => new self($kind, 'IND', true),
            DocumentKind::IdCard => new self($kind, 'ID', true),
            DocumentKind::OtherCollection => new self($kind, 'OC', true),
            DocumentKind::FormTemplate => new self($kind, 'FT', false),
            DocumentKind::FormTransaction => new self($kind, 'FX', true),
            DocumentKind::DisbursementVoucher => new self($kind, 'DV', true),
            DocumentKind::LiquidationReport => new self($kind, 'LR', true),
            DocumentKind::Check => new self($kind, 'CHK', true),
        };
    }

    public function period(int $year): int
    {
        return $this->resetsYearly ? $year : 0;
    }
}
