<?php

namespace Tests\Unit\Forms;

use App\Domain\Forms\CheckLabels;
use App\Domain\Forms\FieldKey;
use App\Domain\Forms\FieldValueFormatter;
use App\Domain\Forms\Ordinal;
use App\Domain\Forms\PdfValueMapper;
use App\Enums\FormInputType;
use App\Enums\PdfFieldType;
use PHPUnit\Framework\TestCase;

class FieldFormattingTest extends TestCase
{
    private CheckLabels $labels;

    protected function setUp(): void
    {
        parent::setUp();
        $this->labels = new CheckLabels('Yes', 'No');
    }

    public function test_each_input_type_has_a_display_format(): void
    {
        $this->assertSame('January 5, 2024', FieldValueFormatter::display(FormInputType::Date, '2024-01-05', $this->labels));
        $this->assertSame('1,234,567.50', FieldValueFormatter::display(FormInputType::Amount, '1234567.5', $this->labels));
        $this->assertSame('0.05', FieldValueFormatter::display(FormInputType::Amount, '0.05', $this->labels));
        $this->assertSame('Yes', FieldValueFormatter::display(FormInputType::Check, true, $this->labels));
        $this->assertSame('No', FieldValueFormatter::display(FormInputType::Check, false, $this->labels));
        $this->assertSame('No', FieldValueFormatter::display(FormInputType::Check, null, $this->labels));
        $this->assertSame('Free text', FieldValueFormatter::display(FormInputType::Text, 'Free text', $this->labels));
        $this->assertSame('', FieldValueFormatter::display(FormInputType::Date, null, $this->labels));
    }

    public function test_ordinals_follow_english_rules(): void
    {
        $this->assertSame(['1st', '2nd', '3rd', '4th', '11th', '12th', '13th', '21st', '22nd', '23rd', '31st', '111th'], array_map(Ordinal::of(...), [1, 2, 3, 4, 11, 12, 13, 21, 22, 23, 31, 111]));
    }

    public function test_field_keys_are_slugs(): void
    {
        $this->assertSame('sub_total', FieldKey::fromText('Sub  Total'));
        $this->assertSame('f_2nd_witness', FieldKey::fromText('2nd witness'));
        $this->assertTrue(FieldKey::isValid('case_number'));
        $this->assertFalse(FieldKey::isValid('Case Number'));
        $this->assertFalse(FieldKey::isValid('1case'));
    }

    public function test_pdf_check_conversion_is_configured_per_mapping(): void
    {
        $this->assertTrue(PdfValueMapper::value(PdfFieldType::Checkbox, true, 'Yes', null, null));
        $this->assertFalse(PdfValueMapper::value(PdfFieldType::Checkbox, false, 'No', null, null));
        $this->assertTrue(PdfValueMapper::value(PdfFieldType::Checkbox, null, 'Some text', null, null));
        $this->assertSame('1', PdfValueMapper::value(PdfFieldType::Text, true, 'Yes', '1', 'X'));
        $this->assertSame('X', PdfValueMapper::value(PdfFieldType::Text, false, 'No', '1', 'X'));
        $this->assertSame('', PdfValueMapper::value(PdfFieldType::Text, false, 'No', null, null));
        $this->assertSame('Employment', PdfValueMapper::value(PdfFieldType::Text, 'Employment', 'Employment', '1', 'X'));
    }
}
