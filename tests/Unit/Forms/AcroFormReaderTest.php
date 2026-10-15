<?php

namespace Tests\Unit\Forms;

use App\Domain\Forms\Pdf\AcroFormReader;
use App\Domain\Forms\Pdf\PdfFormField;
use App\Domain\Forms\Pdf\UnreadablePdf;
use App\Enums\PdfFieldType;
use PHPUnit\Framework\TestCase;
use Tests\Support\FillablePdf;
use Tests\Support\Qpdf;

class AcroFormReaderTest extends TestCase
{
    public function test_field_names_types_and_check_states_are_read(): void
    {
        $fields = AcroFormReader::read(FillablePdf::make(['full_name', 'purpose'], ['is_paid']));

        $this->assertSame([
            ['name' => 'full_name', 'type' => 'text', 'on_state' => null],
            ['name' => 'purpose', 'type' => 'text', 'on_state' => null],
            ['name' => 'is_paid', 'type' => PdfFieldType::Checkbox->value, 'on_state' => 'Yes'],
        ], array_map(fn (PdfFormField $field): array => $field->toArray(), $fields));
    }

    public function test_compressed_object_streams_are_read(): void
    {
        $compressed = Qpdf::run(FillablePdf::make(['full_name'], ['is_paid']), ['--object-streams=generate']);
        if ($compressed === null) {
            $this->markTestSkipped('qpdf is not installed.');
        }

        $this->assertSame(['full_name', 'is_paid'], array_map(fn (PdfFormField $field): string => $field->name, AcroFormReader::read($compressed)));
    }

    public function test_files_that_are_not_fillable_pdfs_are_rejected(): void
    {
        foreach (['plain text', FillablePdf::make([])] as $contents) {
            try {
                AcroFormReader::read($contents);
                $this->fail('Expected an unreadable PDF.');
            } catch (UnreadablePdf) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_encrypted_files_are_rejected(): void
    {
        $encrypted = Qpdf::run(FillablePdf::make(['full_name']), ['--encrypt', '', 'owner', '256', '--']);
        if ($encrypted === null) {
            $this->markTestSkipped('qpdf is not installed.');
        }

        $this->expectException(UnreadablePdf::class);
        AcroFormReader::read($encrypted);
    }
}
