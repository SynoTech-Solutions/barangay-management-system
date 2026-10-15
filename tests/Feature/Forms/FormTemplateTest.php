<?php

namespace Tests\Feature\Forms;

use App\Enums\AuditAction;
use App\Enums\FeatureFlag;
use App\Enums\FormInputType;
use App\Enums\LookupType;
use App\Enums\SystemRole;
use App\Models\AuditEvent;
use App\Models\FormPicklist;
use App\Models\FormPicklistItem;
use App\Models\FormTemplate;
use App\Models\FormTemplateVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesForms;
use Tests\TestCase;

class FormTemplateTest extends TestCase
{
    use CreatesForms, RefreshDatabase;

    public function test_creating_a_rich_text_template_sanitizes_the_body_and_saves_version_one(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $type = $this->lookup(LookupType::FormTemplateType);

        $this->actingAs($secretary)->post(route('forms.templates.store'), $this->richPayload($type->id, [
            'body' => '<p onclick="x()">Hi {{resident.full_name}}</p><script>alert(1)</script>',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $this->inBarangay($barangay);
        $template = FormTemplate::query()->with('currentVersion.fields')->sole();
        $this->assertSame('FT-000001', $template->template_number);
        $this->assertSame(1, $template->currentVersion?->version);
        $this->assertSame('<p>Hi {{resident.full_name}}</p>', $template->currentVersion?->body);
        $this->assertSame(['purpose'], $template->currentVersion?->fields->pluck('key')->all());

        $audit = AuditEvent::query()->where('action', AuditAction::FormTemplateCreated)->sole();
        $this->assertSame(['purpose'], $audit->changes['after']['field_keys'] ?? null);
        $this->assertArrayNotHasKey('body', $audit->changes['after'] ?? []);
    }

    public function test_unknown_placeholders_and_duplicate_names_are_rejected(): void
    {
        [$secretary] = $this->residentDesk();
        $type = $this->lookup(LookupType::FormTemplateType);
        $this->richTemplate($secretary, name: 'Taken name');

        $this->actingAs($secretary)->postJson(route('forms.templates.store'), $this->richPayload($type->id, ['body' => '<p>{{resident.color}} {{field.nope}}</p>']))
            ->assertUnprocessable()->assertJsonPath('error.code', 'form_template_unknown_tokens');
        $this->actingAs($secretary)->postJson(route('forms.templates.store'), $this->richPayload($type->id, ['name' => 'taken NAME']))
            ->assertUnprocessable()->assertJsonPath('error.code', 'form_template_name_taken');
        $this->actingAs($secretary)->postJson(route('forms.templates.store'), $this->richPayload($type->id, ['fields' => [['key' => 'Bad Key', 'label' => 'x', 'input_type' => 'text']]]))
            ->assertUnprocessable()->assertJsonValidationErrors('fields.0.key');
    }

    public function test_editing_content_creates_a_new_immutable_version_and_unchanged_saves_do_not(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $template = $this->richTemplate($secretary);
        $payload = [
            'type_id' => $template->type_id,
            'name' => $template->name,
            'body' => self::BODY,
            'fields' => [['key' => 'purpose', 'label' => 'Purpose', 'input_type' => 'text', 'is_required' => true]],
        ];

        $this->actingAs($secretary)->put(route('forms.templates.update', $template), $payload)->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $this->assertSame(1, FormTemplateVersion::query()->count());

        $this->actingAs($secretary)->put(route('forms.templates.update', $template), [...$payload, 'body' => '<p>New wording for {{field.purpose}}</p>'])->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $versions = FormTemplateVersion::query()->orderBy('version')->get();
        $this->assertSame([1, 2], $versions->pluck('version')->all());
        $this->assertSame($versions[1]->id, $template->refresh()->current_version_id);
        $this->assertStringContainsString('{{resident.full_name}}', (string) $versions[0]->body);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::FormTemplateVersioned)->exists());

        $this->actingAs($secretary)->get(route('forms.templates.show', $template))->assertInertia(fn (Assert $page) => $page
            ->component('forms/templates/Show')
            ->has('versions', 2)
            ->where('currentVersion.version', 2));
        $this->actingAs($secretary)->get(route('forms.templates.versions.preview', [$template, $versions[0]]))->assertInertia(fn (Assert $page) => $page
            ->component('forms/templates/PreviewPrint')
            ->where('template.version', 1)
            ->where('missing', []));
    }

    public function test_saved_versions_are_immutable_in_the_database(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $template = $this->richTemplate($secretary);
        $this->inBarangay($barangay);
        $version = FormTemplateVersion::query()->with('fields')->sole();

        foreach ([
            fn () => DB::table('form_template_versions')->where('id', $version->id)->update(['body' => '<p>tampered</p>']),
            fn () => DB::table('form_template_fields')->where('form_template_version_id', $version->id)->update(['label' => 'Changed']),
            fn () => DB::table('form_template_versions')->where('id', $version->id)->delete(),
        ] as $change) {
            try {
                DB::transaction($change);
                $this->fail('The database accepted a change to a saved version.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('cannot change', $exception->getMessage());
            }
        }

        $this->assertSame($template->current_version_id, $version->id);
    }

    public function test_deactivating_hides_the_template_and_blocks_new_forms(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $template = $this->richTemplate($secretary);

        $this->actingAs($secretary)->post(route('forms.templates.deactivate', $template))->assertRedirect();
        $this->inBarangay($barangay);
        $this->assertFalse($template->refresh()->is_active);
        $this->assertTrue(AuditEvent::query()->where('action', AuditAction::FormTemplateDeactivated)->exists());

        $this->actingAs($secretary)->post(route('forms.transactions.store'), [
            'form_template_id' => $template->id,
            'resident_id' => $this->photographedResident()->id,
            'values' => ['purpose' => 'Work'],
        ])->assertSessionHasErrors('form_template_id');

        $this->actingAs($secretary)->get(route('forms.transactions.create'))->assertInertia(fn (Assert $page) => $page->has('templates', 0));
        $this->actingAs($secretary)->post(route('forms.templates.reactivate', $template))->assertRedirect();
        $this->inBarangay($barangay);
        $this->assertTrue($template->refresh()->is_active);
    }

    public function test_draft_preview_renders_sample_values_and_token_help_lists_placeholders(): void
    {
        [$secretary] = $this->residentDesk();

        $this->actingAs($secretary)->postJson(route('forms.templates.preview'), ['body' => '<p>{{resident.full_name}} / {{field.purpose}} / {{document.given_day}}<script>x</script></p>'])
            ->assertOk()
            ->assertJsonPath('missing', [])
            ->assertJsonPath('html', '<p>'.__('forms.samples.resident.full_name').' / [purpose] / '.now()->format('j').(now()->format('S')).'</p>');

        $this->actingAs($secretary)->get(route('forms.tokens'))->assertInertia(fn (Assert $page) => $page
            ->component('forms/Tokens')
            ->has('tokens.0'));
    }

    public function test_table_fields_need_pick_lists_and_the_rich_text_flag_gates_the_kind(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->turnOff(FeatureFlag::FormsRichTextTemplates, $barangay);
        $type = $this->lookup(LookupType::FormTemplateType);

        $this->actingAs($secretary)->post(route('forms.templates.store'), $this->richPayload($type->id))->assertSessionHasErrors('kind');
        $this->actingAs($secretary)->postJson(route('forms.templates.preview'), ['body' => '<p>x</p>'])->assertNotFound();
        $this->actingAs($secretary)->post(route('forms.templates.store'), $this->richPayload($type->id, [
            'kind' => 'pdf',
            'pdf' => $this->pdfUpload(),
            'body' => null,
            'fields' => [['key' => 'case_type', 'label' => 'Case type', 'input_type' => FormInputType::Table->value]],
        ]))->assertSessionHasErrors('fields.0.picklist_id');
    }

    public function test_pick_lists_are_maintained_per_barangay_and_values_are_hidden_not_deleted(): void
    {
        [$secretary, $barangay] = $this->residentDesk();

        $this->actingAs($secretary)->post(route('forms.picklists.store'), ['name' => 'Case types'])->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $list = FormPicklist::query()->sole();
        $this->actingAs($secretary)->post(route('forms.picklists.items.store', $list), ['label' => 'Unjust vexation'])->assertSessionHasNoErrors();
        $this->actingAs($secretary)->postJson(route('forms.picklists.items.store', $list), ['label' => 'unjust  VEXATION'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'form_picklist_item_taken');

        $this->inBarangay($barangay);
        $item = FormPicklistItem::query()->sole();
        $this->actingAs($secretary)->put(route('forms.picklists.items.update', [$list, $item]), ['label' => 'Unjust vexation', 'is_active' => false])->assertSessionHasNoErrors();
        $this->inBarangay($barangay);
        $this->assertFalse($item->refresh()->is_active);
        $this->assertSame(3, AuditEvent::query()->where('action', AuditAction::FormPicklistSaved)->count());

        $this->actingAs($secretary)->get(route('forms.picklists.index'))->assertInertia(fn (Assert $page) => $page
            ->component('forms/picklists/Index')
            ->where('picklists.0.items.0.is_active', false));

        [$outsider] = $this->residentDesk();
        $this->actingAs($outsider)->post(route('forms.picklists.items.store', $list), ['label' => 'Theft'])->assertNotFound();

        $this->turnOff(FeatureFlag::FormsTablePicklists, $barangay);
        $other = $this->barangayUser(SystemRole::BarangayAdmin, $barangay);
        $this->actingAs($other)->get(route('forms.picklists.index'))->assertNotFound();
    }

    public function test_access_follows_permissions_flags_and_tenancy(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $template = $this->richTemplate($secretary);

        $clerk = $this->barangayUser(SystemRole::Clerk, $barangay);
        $this->actingAs($clerk)->get(route('forms.templates.index'))->assertForbidden();
        $this->actingAs($clerk)->get(route('forms.transactions.create'))->assertOk();
        $viewer = $this->barangayUser(SystemRole::Viewer, $barangay);
        $this->actingAs($viewer)->get(route('forms.transactions.index'))->assertOk();
        $this->actingAs($viewer)->get(route('forms.transactions.create'))->assertForbidden();
        $this->actingAs($secretary)->get(route('forms.templates.index'))->assertOk();

        [$outsider] = $this->residentDesk();
        $this->actingAs($outsider)->get(route('forms.templates.show', $template))->assertNotFound();
        $this->actingAs($outsider)->get(route('forms.templates.edit', $template))->assertNotFound();
    }

    public function test_the_whole_module_answers_not_found_when_forms_engine_is_off(): void
    {
        [$secretary, $barangay] = $this->residentDesk();
        $this->turnOff(FeatureFlag::FormsEngine, $barangay);

        foreach (['forms.templates.index', 'forms.transactions.index', 'forms.transactions.create', 'forms.tokens'] as $route) {
            $this->actingAs($secretary)->get(route($route))->assertNotFound();
        }
    }
}
