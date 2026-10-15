<?php

namespace Tests\Feature\Forms;

use App\Domain\Forms\FieldDraft;
use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Enums\FormInputType;
use App\Enums\IssuedDocumentStatus;
use App\Enums\SignatoryOffice;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\BarangayClearance;
use App\Models\Blotter;
use App\Models\FormPicklist;
use App\Models\FormPicklistItem;
use App\Models\FormTransaction;
use App\Models\Signatory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesForms;
use Tests\TestCase;

class FormTransactionTest extends TestCase
{
    use CreatesForms, RefreshDatabase;

    public function test_issuing_numbers_the_form_snapshots_values_and_opens_the_print_page(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        Signatory::query()->create(['office' => SignatoryOffice::PunongBarangay, 'name' => 'Pedro Reyes', 'title' => 'Punong Barangay']);
        $template = $this->richTemplate($secretary);
        $resident = $this->photographedResident(['first_name' => 'Juana', 'last_name' => 'Dela Cruz']);

        $this->actingAs($secretary)->post(route('forms.transactions.store'), [
            'form_template_id' => $template->id,
            'resident_id' => $resident->id,
            'values' => ['purpose' => 'Employment'],
            'or_number' => 'or-77',
            'amount' => '50',
        ])->assertSessionHasNoErrors()->assertRedirect(route('forms.transactions.print', FormTransaction::acrossBarangays()->sole()));

        $this->inBarangay($barangay);
        $transaction = FormTransaction::query()->sole();
        $this->assertSame('FX-'.now()->year.'-000001', $transaction->transaction_number);
        $this->assertSame($template->current_version_id, $transaction->form_template_version_id);
        $this->assertSame('Pedro Reyes', $transaction->certified_by_name);
        $this->assertSame(1, $transaction->snapshot['v']);
        $this->assertSame('Juana Dela Cruz', $transaction->snapshot['tokens']['resident.full_name']);

        $audit = AuditEvent::query()->where('action', AuditAction::FormTransactionIssued)->sole();
        $this->assertSame(['purpose'], $audit->changes['after']['field_keys'] ?? null);
        $this->assertStringNotContainsString('Employment', (string) json_encode($audit->changes));

        $this->actingAs($secretary)->get(route('forms.transactions.print', $transaction))->assertInertia(fn (Assert $page) => $page
            ->component('forms/transactions/Print')
            ->where('document.is_reprint', false)
            ->where('document.html', fn (string $html) => str_contains($html, 'Juana Dela Cruz') && str_contains($html, 'for Employment.')));
        $this->actingAs($secretary)->get(route('forms.transactions.print', $transaction))->assertInertia(fn (Assert $page) => $page->where('document.is_reprint', true));
        $this->assertSame(2, AuditEvent::query()->where('action', AuditAction::FormTransactionPrinted)->count());
        $this->assertSame(2, $transaction->refresh()->print_count);
    }

    public function test_without_auto_print_the_list_opens_after_saving(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->turnOff(FeatureFlag::FormsAutoPrintOnSave, $barangay);
        $template = $this->richTemplate($secretary);

        $this->actingAs($secretary)->post(route('forms.transactions.store'), [
            'form_template_id' => $template->id,
            'resident_id' => $this->photographedResident()->id,
            'values' => ['purpose' => 'Scholarship'],
        ])->assertRedirect(route('forms.transactions.index'));
    }

    public function test_old_forms_keep_their_version_and_snapshot_after_template_and_resident_changes(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $template = $this->richTemplate($secretary);
        $resident = $this->photographedResident(['first_name' => 'Juana', 'last_name' => 'Dela Cruz']);
        $this->actingAs($secretary)->post(route('forms.transactions.store'), [
            'form_template_id' => $template->id,
            'resident_id' => $resident->id,
            'values' => ['purpose' => 'Employment'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($secretary)->put(route('forms.templates.update', $template), [
            'type_id' => $template->type_id,
            'name' => $template->name,
            'body' => '<p>Rewritten: {{field.purpose}} only.</p>',
            'fields' => [['key' => 'purpose', 'label' => 'Purpose', 'input_type' => 'text', 'is_required' => true]],
        ])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $resident->forceFill(['first_name' => 'Renamed', 'full_name' => 'Dela Cruz, Renamed'])->save();
        $transaction = FormTransaction::query()->sole();

        $this->actingAs($secretary)->get(route('forms.transactions.print', $transaction))->assertInertia(fn (Assert $page) => $page
            ->where('document.version', 1)
            ->where('document.html', fn (string $html) => str_contains($html, 'This certifies that Juana Dela Cruz') && ! str_contains($html, 'Renamed') && ! str_contains($html, 'Rewritten')));
    }

    public function test_values_are_validated_by_input_type(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->inBarangay($barangay);
        $picklist = FormPicklist::factory()->create(['name' => 'Case types']);
        $item = FormPicklistItem::factory()->create(['form_picklist_id' => $picklist->id, 'label' => 'Unjust vexation']);
        $hidden = FormPicklistItem::factory()->create(['form_picklist_id' => $picklist->id, 'is_active' => false]);
        $witness = $this->photographedResident(['first_name' => 'Wally', 'last_name' => 'Witness']);
        $template = $this->richTemplate($secretary, [
            new FieldDraft('remarks', 'Remarks', FormInputType::Text, false, null),
            new FieldDraft('witness', 'Witness', FormInputType::Resident, true, null),
            new FieldDraft('case_type', 'Case type', FormInputType::Table, true, $picklist->id),
            new FieldDraft('settled', 'Settled', FormInputType::Check, false, null),
            new FieldDraft('hearing', 'Hearing date', FormInputType::Date, true, null),
            new FieldDraft('fee', 'Fee', FormInputType::Amount, true, null),
        ], '<p>{{field.witness}} {{field.case_type}} {{field.settled}} {{field.hearing}} {{field.fee}}</p>');
        $resident = $this->photographedResident();

        $this->actingAs($secretary)->post(route('forms.transactions.store'), [
            'form_template_id' => $template->id,
            'resident_id' => $resident->id,
            'values' => ['remarks' => str_repeat('x', 2001), 'witness' => 999999, 'case_type' => $hidden->id, 'settled' => 'maybe', 'hearing' => '01/05/2024', 'fee' => '1.234'],
        ])->assertSessionHasErrors(['values.remarks', 'values.witness', 'values.case_type', 'values.settled', 'values.hearing', 'values.fee']);

        $this->actingAs($secretary)->post(route('forms.transactions.store'), [
            'form_template_id' => $template->id,
            'resident_id' => $resident->id,
            'values' => ['witness' => $witness->id, 'case_type' => $item->id, 'settled' => true, 'hearing' => '2024-01-05', 'fee' => '1234.5'],
        ])->assertSessionHasNoErrors();

        $this->inBarangay($barangay);
        $transaction = FormTransaction::query()->sole();
        $this->actingAs($secretary)->get(route('forms.transactions.print', $transaction))->assertInertia(fn (Assert $page) => $page
            ->where('document.html', '<p>Wally Witness Unjust vexation Yes January 5, 2024 1,234.50</p>'));
    }

    public function test_or_numbers_share_the_official_receipt_registry(): void
    {
        [$secretary] = $this->residentDesk();
        BarangayClearance::factory()->create(['or_number' => 'OR-500']);
        $template = $this->richTemplate($secretary);

        $this->actingAs($secretary)->post(route('forms.transactions.store'), [
            'form_template_id' => $template->id,
            'resident_id' => $this->photographedResident()->id,
            'values' => ['purpose' => 'Work'],
            'or_number' => 'or-500',
        ])->assertSessionHasErrors('or_number');
    }

    public function test_respondents_with_an_open_complaint_are_held(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $template = $this->richTemplate($secretary);
        $respondent = $this->photographedResident();
        Blotter::factory()->against($respondent)->create();

        $this->actingAs($secretary)->postJson(route('forms.transactions.store'), [
            'form_template_id' => $template->id,
            'resident_id' => $respondent->id,
            'values' => ['purpose' => 'Work'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'resident_on_hold');

        $this->inBarangay($barangay);
        $this->assertSame(0, FormTransaction::query()->count());
    }

    public function test_edits_are_allowed_only_before_release_and_bulk_actions_are_all_or_nothing(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $template = $this->richTemplate($secretary);
        $this->actingAs($secretary)->post(route('forms.transactions.store'), [
            'form_template_id' => $template->id,
            'resident_id' => $this->photographedResident()->id,
            'values' => ['purpose' => 'Work'],
        ])->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $pending = FormTransaction::query()->sole();
        $cancelled = FormTransaction::factory()->cancelled()->create();

        $this->actingAs($secretary)->get(route('forms.transactions.edit', $pending))->assertInertia(fn (Assert $page) => $page
            ->component('forms/transactions/Form')
            ->where('values.purpose', 'Work'));
        $this->actingAs($secretary)->put(route('forms.transactions.update', $pending), ['values' => ['purpose' => 'Travel'], 'amount' => '20'])->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $this->assertSame('Travel', $pending->refresh()->snapshot['fields'][0]['value']);
        $updated = AuditEvent::query()->where('action', AuditAction::FormTransactionUpdated)->sole();
        $this->assertSame(['purpose'], $updated->changes['after']['changed_field_keys'] ?? null);
        $this->assertStringNotContainsString('Travel', (string) json_encode($updated->changes));

        $this->actingAs($secretary)->post(route('forms.transactions.release'), ['ids' => [$pending->id, $cancelled->id]])->assertRedirect();
        $this->inBarangay($barangay);
        $this->assertTrue($pending->refresh()->isPending());

        $this->actingAs($secretary)->post(route('forms.transactions.release'), ['ids' => [$pending->id]])->assertRedirect();
        $this->inBarangay($barangay);
        $this->assertSame(IssuedDocumentStatus::Released, $pending->refresh()->status);
        $this->actingAs($secretary)->putJson(route('forms.transactions.update', $pending), ['values' => ['purpose' => 'Late']])
            ->assertUnprocessable()->assertJsonPath('error.code', 'document_not_editable');
        $this->actingAs($secretary)->post(route('forms.transactions.cancel'), ['ids' => [$pending->id]])->assertSessionHasErrors('reason');
        $this->actingAs($secretary)->getJson(route('forms.transactions.print', $cancelled))->assertUnprocessable()->assertJsonPath('error.code', 'document_cancelled');
    }

    public function test_report_filters_export_print_and_dashboard_counter(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $template = $this->richTemplate($secretary);
        $this->actingAs($secretary)->post(route('forms.transactions.store'), [
            'form_template_id' => $template->id,
            'resident_id' => $this->photographedResident(['first_name' => 'Exported'])->id,
            'values' => ['purpose' => '=HYPERLINK("x")'],
            'amount' => '35.50',
        ])->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        FormTransaction::factory()->released()->create();

        $this->actingAs($secretary)->get(route('forms.transactions.index', ['status' => 'issued']))->assertInertia(fn (Assert $page) => $page
            ->component('forms/transactions/Index')
            ->has('transactions.data', 1)
            ->where('total', '35.50'));

        $csv = $this->actingAs($secretary)->get(route('forms.transactions.export', ['template_id' => $template->id]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Purpose', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::Exported)->exists());

        $this->actingAs($secretary)->get(route('forms.transactions.list-print', ['template_id' => $template->id]))->assertInertia(fn (Assert $page) => $page
            ->component('forms/transactions/ListPrint')
            ->has('rows', 1)
            ->where('fieldColumns.0.key', 'purpose'));

        $this->actingAs($secretary)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('counters', fn ($counters) => collect($counters)->contains(fn (array $counter) => $counter['key'] === 'form-transactions-for-release' && $counter['count'] === 1)));
    }

    public function test_resident_page_lists_forms_and_access_is_isolated(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->inBarangay($barangay);
        $transaction = FormTransaction::factory()->create();

        $this->actingAs($secretary)->get(route('residents.show', $transaction->resident_id))->assertInertia(fn (Assert $page) => $page
            ->has('forms', 1)
            ->where('forms.0.transaction_number', $transaction->transaction_number));

        $treasurer = $this->barangayUser(SystemRole::Treasurer, $barangay);
        $this->actingAs($treasurer)->post(route('forms.transactions.release'), ['ids' => [$transaction->id]])->assertForbidden();

        [$outsider] = $this->residentDesk();
        $this->actingAs($outsider)->get(route('forms.transactions.print', $transaction))->assertNotFound();
        $this->actingAs($outsider)->get(route('forms.transactions.edit', $transaction))->assertNotFound();
        $this->actingAs($outsider)->post(route('forms.transactions.cancel'), ['ids' => [$transaction->id], 'reason' => 'x'])->assertSessionHasErrors('ids.0');
    }

    public function test_release_routes_disappear_when_the_release_step_is_off(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->turnOff(FeatureFlag::DocumentReleaseStep, $barangay);
        $template = $this->richTemplate($secretary);

        $this->actingAs($secretary)->post(route('forms.transactions.store'), [
            'form_template_id' => $template->id,
            'resident_id' => $this->photographedResident()->id,
            'values' => ['purpose' => 'Work'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->postJson(route('forms.transactions.release'), ['ids' => [1]])->assertNotFound();

        $this->inBarangay($barangay);
        $this->assertSame(IssuedDocumentStatus::Released, FormTransaction::query()->sole()->status);
    }
}
