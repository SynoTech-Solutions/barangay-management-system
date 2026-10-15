<?php

namespace Tests\Unit\Domain;

use App\Domain\Numbering\DocumentNumberFormatter;
use App\Domain\Numbering\NumberingRule;
use App\Enums\DocumentKind;
use PHPUnit\Framework\TestCase;

class DocumentNumberFormatterTest extends TestCase
{
    public function test_yearly_documents_include_the_year(): void
    {
        $rule = NumberingRule::for(DocumentKind::BarangayClearance);

        $this->assertSame('BC-2026-000042', DocumentNumberFormatter::format($rule, $rule->period(2026), 42, 6));
    }

    public function test_continuous_documents_omit_the_year(): void
    {
        $rule = NumberingRule::for(DocumentKind::Resident);

        $this->assertSame(0, $rule->period(2026));
        $this->assertSame('RES-0007', DocumentNumberFormatter::format($rule, $rule->period(2026), 7, 4));
    }

    public function test_numbers_wider_than_the_width_are_not_truncated(): void
    {
        $rule = NumberingRule::for(DocumentKind::Resident);

        $this->assertSame('RES-123456', DocumentNumberFormatter::format($rule, 0, 123456, 3));
    }

    public function test_every_kind_has_a_unique_prefix(): void
    {
        $prefixes = array_map(fn (DocumentKind $kind): string => NumberingRule::for($kind)->prefix, DocumentKind::cases());

        $this->assertSame(count($prefixes), count(array_unique($prefixes)));
    }
}
