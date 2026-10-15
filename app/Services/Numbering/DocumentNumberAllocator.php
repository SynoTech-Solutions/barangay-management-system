<?php

namespace App\Services\Numbering;

use App\Domain\Numbering\DocumentNumberFormatter;
use App\Domain\Numbering\NumberingRule;
use App\Enums\DocumentKind;
use App\Support\Config\BmsSettings;
use App\Tenancy\CurrentBarangay;
use Illuminate\Support\Facades\DB;

final readonly class DocumentNumberAllocator
{
    public function __construct(
        private BmsSettings $settings,
        private CurrentBarangay $currentBarangay,
    ) {}

    public function next(DocumentKind $kind, int $year): string
    {
        $rule = NumberingRule::for($kind);
        $period = $rule->period($year);
        $barangayId = $this->currentBarangay->require()->id;

        $row = DB::selectOne(
            'insert into document_sequences (barangay_id, kind, period, last_value, created_at, updated_at)
             values (?, ?, ?, 1, now(), now())
             on conflict (barangay_id, kind, period)
             do update set last_value = document_sequences.last_value + 1, updated_at = now()
             returning last_value',
            [$barangayId, $kind->value, $period],
        );

        return DocumentNumberFormatter::format($rule, $period, (int) $row->last_value, $this->settings->documentNumberWidth);
    }
}
