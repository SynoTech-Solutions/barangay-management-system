<?php

namespace Tests\Feature\Forms;

use App\Domain\Forms\FieldDraft;
use App\Domain\Forms\Pdf\AcroFormReader;
use App\Domain\Forms\Pdf\UnreadablePdf;
use App\Domain\Forms\PdfMappingDraft;
use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Enums\FormInputType;
use App\Enums\LookupType;
use App\Models\AuditEvent;
use App\Models\FormPdfMapping;
use App\Models\FormTemplate;
use App\Models\FormTransaction;
use App\Services\Forms\Pdf\PdfFormFiller;
use App\Services\Forms\Pdf\QpdfFormFiller;
use App\Services\Forms\Pdf\UnconfiguredPdfFormFiller;
use App\Support\StoragePaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesForms;
use Tests\Support\FakePdfFormFiller;
use Tests\Support\FillablePdf;
use Tests\Support\Qpdf;
use Tests\TestCase;

class FormPdfTest extends TestCase
{
    use CreatesForms, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(StoragePaths::PRIVATE_DISK);
    }

    public function test_uploaded_pdfs_list_their_fields_and_bad_files_are_refused(): void
    {
        [$secretary] = $this->residentDesk();

        $this->actingAs($secretary)->post(route('forms.templates.inspect-pdf'), ['pdf' => $this->pdfUpload()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('fields.0', ['name' => 'applicant', 'type' => 'text', 'on_state' => null])
            ->assertJsonPath('fields.3', ['name' => 'is_paid', 'type' => 'checkbox', 'on_state' => 'Yes']);

        $this->actingAs($secretary)->post(route('forms.templates.inspect-pdf'), ['pdf' => UploadedFile::fake()->createWithContent('flat.pdf', FillablePdf::make([]))], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'pdf_form_unreadable');
        $this->actingAs($secretary)->post(route('forms.templates.inspect-pdf'), ['pdf' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('pdf');
    }

    public function test_a_pdf_template_stores_the_file_privately_and_keeps_only_mappings_to_real_fields(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $type = $this->lookup(LookupType::FormTemplateType);

        $this->actingAs($secretary)->post(route('forms.templates.store'), [
            'type_id' => $type->id,
            'name' => 'Building permit application',
            'kind' => 'pdf',
            'pdf' => $this->pdfUpload(),
            'fields' => [['key' => 'purpose', 'label' => 'Purpose', 'input_type' => 'text', 'is_required' => true]],
            'mappings' => [
                ['pdf_field' => 'applicant', 'token' => 'resident.full_name'],
                ['pdf_field' => 'purpose', 'token' => 'field.purpose'],
                ['pdf_field' => 'not_in_pdf', 'token' => 'resident.age'],
            ],
        ])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $template = FormTemplate::query()->with('currentVersion')->sole();
        $path = (string) $template->currentVersion?->pdf_path;
        $this->assertStringStartsWith("barangays/{$barangay->id}/form-templates/", $path);
        Storage::disk(StoragePaths::PRIVATE_DISK)->assertExists($path);
        $this->assertSame(['applicant', 'purpose'], FormPdfMapping::query()->orderBy('id')->pluck('pdf_field')->all());

        $this->actingAs($secretary)->postJson(route('forms.templates.store'), [
            'type_id' => $type->id,
            'name' => 'Other permit',
            'kind' => 'pdf',
            'pdf' => $this->pdfUpload(),
            'fields' => [],
            'mappings' => [['pdf_field' => 'applicant', 'token' => 'resident.color']],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'form_template_unknown_tokens');
    }

    public function test_printing_fills_the_pdf_with_snapshot_values_and_configured_check_text(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $filler = new FakePdfFormFiller;
        $this->app->instance(PdfFormFiller::class, $filler);
        $template = $this->pdfTemplate($secretary, [
            new FieldDraft('purpose', 'Purpose', FormInputType::Text, true, null),
            new FieldDraft('paid', 'Paid', FormInputType::Check, false, null),
        ], [
            new PdfMappingDraft('applicant', 'resident.full_name', null, null),
            new PdfMappingDraft('purpose', 'field.purpose', null, null),
            new PdfMappingDraft('is_paid', 'field.paid', null, null),
            new PdfMappingDraft('paid_text', 'field.paid', 'X', '-'),
        ]);

        $this->actingAs($secretary)->post(route('forms.transactions.store'), [
            'form_template_id' => $template->id,
            'resident_id' => $this->photographedResident(['first_name' => 'Juana', 'last_name' => 'Dela Cruz'])->id,
            'values' => ['purpose' => 'Fence', 'paid' => true],
        ])->assertRedirect(route('forms.transactions.print', 1));

        $this->inBarangay($barangay);
        $transaction = FormTransaction::query()->sole();
        $this->actingAs($secretary)->get(route('forms.transactions.print', $transaction))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame(['applicant' => 'Juana Dela Cruz', 'purpose' => 'Fence', 'is_paid' => true, 'paid_text' => 'X'], $filler->fills[0]);
        $this->assertSame(1, $transaction->refresh()->print_count);
        $this->assertSame(1, AuditEvent::query()->where('action', AuditAction::FormTransactionPrinted)->count());
    }

    public function test_an_unconfigured_filler_reports_a_clear_error_and_counts_no_print(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->app->instance(PdfFormFiller::class, new UnconfiguredPdfFormFiller);
        $template = $this->pdfTemplate($secretary, [], [new PdfMappingDraft('applicant', 'resident.full_name', null, null)]);
        $this->inBarangay($barangay);
        $transaction = FormTransaction::factory()->create(['form_template_id' => $template->id, 'form_template_version_id' => $template->current_version_id]);

        $this->actingAs($secretary)->getJson(route('forms.transactions.print', $transaction))
            ->assertServiceUnavailable()->assertJsonPath('error.code', 'pdf_filler_not_configured');
        $this->assertSame(0, $transaction->refresh()->print_count);
    }

    public function test_the_qpdf_adapter_fills_and_flattens(): void
    {
        $binary = Qpdf::binary();
        if ($binary === null) {
            $this->markTestSkipped('qpdf is not installed.');
        }

        $filled = (new QpdfFormFiller($binary, 30))->fill(FillablePdf::make(['applicant'], ['is_paid']), ['applicant' => 'Juana Dela Cruz & Niño', 'is_paid' => true, 'missing' => 'ignored']);

        $readable = Qpdf::run($filled, ['--qdf', '--object-streams=disable']);
        $this->assertNotNull($readable);
        $this->assertStringContainsString('(Juana Dela Cruz & Ni', $readable);
        $this->expectException(UnreadablePdf::class);
        AcroFormReader::read($filled);
    }

    public function test_pdf_templates_follow_their_flag(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $template = $this->pdfTemplate($secretary, [], [new PdfMappingDraft('applicant', 'resident.full_name', null, null)]);
        $this->turnOff(FeatureFlag::PdfFormTemplates, $barangay);

        $this->actingAs($secretary)->postJson(route('forms.templates.inspect-pdf'), ['pdf' => $this->pdfUpload()])->assertNotFound();
        $this->actingAs($secretary)->postJson(route('forms.transactions.store'), [
            'form_template_id' => $template->id,
            'resident_id' => $this->photographedResident()->id,
            'values' => [],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'form_template_unavailable');
    }
}
