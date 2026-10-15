<?php

namespace Tests\Feature\ImportBundle;

use App\Enums\AuditAction;
use App\Enums\BundleEntity;
use App\Enums\DocumentKind;
use App\Enums\FeatureFlag;
use App\Enums\ImportRunStatus;
use App\Enums\IssuedDocumentStatus;
use App\Enums\LookupType;
use App\Enums\SystemRole;
use App\Models\Barangay;
use App\Models\ImportRun;
use App\Services\Features\FeatureFlags;
use App\Services\ImportBundle\ImportOptions;
use App\Services\Numbering\DocumentNumberAllocator;
use App\Tenancy\CurrentBarangay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ImportsBundles;
use Tests\Support\FillablePdf;
use Tests\Support\SampleBundle;
use Tests\TestCase;

class BundleImportTest extends TestCase
{
    use ImportsBundles, RefreshDatabase;

    private const array TENANT_TABLES = [
        'lookup_values', 'puroks', 'users', 'residents', 'resident_sector', 'family_members', 'resident_educations',
        'resident_work_histories', 'id_cards', 'barangay_clearances', 'indigency_certificates', 'businesses', 'business_clearances', 'cedulas',
        'blotters', 'blotter_parties', 'blotter_photos', 'collection_types', 'other_collections', 'accounts', 'budgets',
        'budget_versions', 'budget_lines', 'vendors', 'vouchers', 'voucher_items', 'bank_accounts', 'checks', 'liquidations',
        'liquidation_items', 'form_picklists', 'form_picklist_items', 'form_templates', 'form_template_versions',
        'form_template_fields', 'form_transactions', 'sms_groups', 'sms_group_members', 'sms_templates', 'official_receipts',
        'document_sequences', 'import_id_map', 'import_runs', 'color_categories', 'resident_political_tags', 'audit_events',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBundleImports();
    }

    public function test_full_import_reports_exact_counts_per_entity(): void
    {
        $barangay = $this->onboardedBarangay();

        $report = $this->importBundle($barangay, SampleBundle::make()->write());

        $this->assertSame(ImportRunStatus::Completed, $report->status);
        $this->assertEntity($report, 'lookups', ['read' => 2, 'imported' => 2, 'errors' => 0]);
        $this->assertEntity($report, 'puroks', ['read' => 2, 'imported' => 2]);
        $this->assertEntity($report, 'users', ['read' => 2, 'imported' => 2]);
        $this->assertEntity($report, 'color_categories', ['read' => 1, 'imported' => 0, 'skipped' => 1]);
        $this->assertEntity($report, 'residents', ['read' => 4, 'imported' => 3, 'errors' => 1, 'warnings' => 3]);
        $this->assertEntity($report, 'family_members', ['read' => 2, 'imported' => 1, 'errors' => 1]);
        $this->assertEntity($report, 'educations', ['read' => 1, 'imported' => 1]);
        $this->assertEntity($report, 'work_histories', ['read' => 1, 'imported' => 1]);
        $this->assertEntity($report, 'id_cards', ['read' => 2, 'imported' => 2, 'warnings' => 0]);
        $this->assertEntity($report, 'barangay_clearances', ['read' => 3, 'imported' => 3, 'warnings' => 1]);
        $this->assertEntity($report, 'indigency_certificates', ['read' => 1, 'imported' => 1]);
        $this->assertEntity($report, 'businesses', ['read' => 1, 'imported' => 1]);
        $this->assertEntity($report, 'business_clearances', ['read' => 1, 'imported' => 1]);
        $this->assertEntity($report, 'cedulas', ['read' => 2, 'imported' => 2, 'warnings' => 1]);
        $this->assertEntity($report, 'blotters', ['read' => 2, 'imported' => 2, 'warnings' => 0]);
        $this->assertEntity($report, 'collection_types', ['read' => 1, 'imported' => 1]);
        $this->assertEntity($report, 'other_collections', ['read' => 2, 'imported' => 1, 'errors' => 1]);
        $this->assertEntity($report, 'accounts', ['read' => 3, 'imported' => 3]);
        $this->assertEntity($report, 'budgets', ['read' => 1, 'imported' => 1, 'warnings' => 1]);
        $this->assertEntity($report, 'bank_accounts', ['read' => 1, 'imported' => 1]);
        $this->assertEntity($report, 'vendors', ['read' => 1, 'imported' => 1]);
        $this->assertEntity($report, 'vouchers', ['read' => 1, 'imported' => 1]);
        $this->assertEntity($report, 'checks', ['read' => 1, 'imported' => 1]);
        $this->assertEntity($report, 'liquidations', ['read' => 1, 'imported' => 1]);
        $this->assertEntity($report, 'form_picklists', ['read' => 1, 'imported' => 1]);
        $this->assertEntity($report, 'form_templates', ['read' => 1, 'imported' => 1]);
        $this->assertEntity($report, 'form_transactions', ['read' => 1, 'imported' => 1]);
        $this->assertEntity($report, 'sms_groups', ['read' => 1, 'imported' => 1, 'warnings' => 1]);
        $this->assertEntity($report, 'sms_templates', ['read' => 1, 'imported' => 1]);

        foreach ($report->entities as $entity => $counts) {
            $this->assertSame($counts['read'], $counts['imported'] + $counts['skipped'] + $counts['errors'], "Entity {$entity} does not reconcile.");
        }

        $this->assertSame(3, $this->rows('residents', $barangay));
        $this->assertSame(2, $this->rows('users', $barangay));
        $this->assertSame(2, $this->rows('id_cards', $barangay));
        $this->assertSame(3, $this->rows('barangay_clearances', $barangay));
        $this->assertSame(1, $this->rows('blotter_photos', $barangay));
        $this->assertSame(2, $this->rows('blotter_parties', $barangay));
        $this->assertSame(1, $this->rows('voucher_items', $barangay));
        $this->assertSame(1, $this->rows('liquidation_items', $barangay));
        $this->assertSame(2, $this->rows('form_template_fields', $barangay));
        $this->assertSame(2, $this->rows('form_picklist_items', $barangay));
        $this->assertSame(2, $this->rows('sms_group_members', $barangay));
        $this->assertSame(1, $this->rows('budget_lines', $barangay));

        $run = ImportRun::acrossBarangays()->where('barangay_id', $barangay->id)->sole();
        $this->assertSame(ImportRunStatus::Completed, $run->status);
        $this->assertSame(1, $run->report['v'] ?? null);
        $this->assertSame('test-writer', $run->options['bundle']['generator'] ?? null);
        $this->assertEquals($report->totals, $run->report['totals'] ?? null);
        $this->assertSame($report->problemsPath, $run->problems_path);
    }

    public function test_records_keep_values_in_the_new_vocabulary(): void
    {
        $barangay = $this->onboardedBarangay();

        $this->importBundle($barangay, SampleBundle::make()->write());

        $juan = $this->resident($barangay, 'RES-000010');
        $maria = $this->resident($barangay, 'RES-000011');
        $this->assertSame('Sample, Juan Test', $juan->full_name);
        $this->assertSame('1960-01-15', $juan->birthdate);
        $this->assertSame('09170000001', $juan->mobile_number);
        $this->assertSame('170.0', $juan->height_cm);
        $this->assertSame('2019-03-05 01:15:00', $juan->created_at);
        $this->assertSame($maria->id, $juan->spouse_id);
        $this->assertSame($juan->id, $maria->spouse_id);
        $this->assertSame(['Farmer', 'Women'], DB::table('resident_sector')->join('lookup_values', 'lookup_values.id', '=', 'resident_sector.sector_id')->where('resident_id', $juan->id)->orderBy('label')->pluck('label')->all());
        $this->assertSame(1985, (int) DB::table('family_members')->where('barangay_id', $barangay->id)->value('birth_year'));
        $this->assertNull(DB::table('resident_work_histories')->where('barangay_id', $barangay->id)->value('ended_on'));

        $card = DB::table('id_cards')->where('barangay_id', $barangay->id)->where('card_number', 'ID-2024-000010')->sole();
        $this->assertSame(IssuedDocumentStatus::Released->value, $card->status);
        $this->assertSame('OR-100', $card->or_number);
        $this->assertSame('100.00', $card->amount);

        $clearance = DB::table('barangay_clearances')->where('barangay_id', $barangay->id)->where('control_number', 'BCC-2024-000007')->sole();
        $this->assertSame('10.10', $clearance->doc_stamp);
        $this->assertSame('2024-12-31', $clearance->expires_on);

        $voucher = DB::table('vouchers')->where('barangay_id', $barangay->id)->where('voucher_number', 'DV-2024-000006')->sole();
        $this->assertSame('released', $voucher->status);
        $this->assertNotNull($voucher->approved_at);
        $this->assertSame('4300.00', DB::table('liquidation_items')->where('barangay_id', $barangay->id)->value('actual_amount'));

        $accounts = DB::table('accounts')->where('barangay_id', $barangay->id)->whereIn('code', ['2', '9-01', '9-01-01'])->pluck('id', 'code');
        $this->assertNull(DB::table('accounts')->where('barangay_id', $barangay->id)->where('code', '9')->first());
        $this->assertSame($accounts['2'], DB::table('accounts')->where('id', $accounts['9-01'])->value('parent_id'));

        $template = DB::table('form_templates')->where('barangay_id', $barangay->id)->sole();
        $body = (string) DB::table('form_template_versions')->where('id', $template->current_version_id)->value('body');
        $this->assertStringContainsString('{{resident.full_name}}', $body);
        $this->assertStringNotContainsString('<script>', $body);
        $snapshot = json_decode((string) DB::table('form_transactions')->where('barangay_id', $barangay->id)->value('snapshot'), true);
        $this->assertSame('Employment', $snapshot['fields'][0]['value'] ?? null);
        $this->assertSame('Juan Test Sample', $snapshot['tokens']['resident.full_name'] ?? null);

        $this->assertSame(['+639170000009'], DB::table('sms_group_members')->where('barangay_id', $barangay->id)->whereNotNull('mobile_number')->pluck('mobile_number')->all());
        $this->assertNull(DB::table('cedulas')->where('barangay_id', $barangay->id)->whereNull('source_number')->where('form_number', 'CC-1')->where('cedula_number', '<>', 'CTC-2024-000031')->first());
        $this->assertNotNull(DB::table('cedulas')->where('barangay_id', $barangay->id)->whereNull('form_number')->value('import_note'));
    }

    public function test_lookup_labels_match_shared_values_or_create_barangay_values(): void
    {
        $barangay = $this->onboardedBarangay();

        $this->importBundle($barangay, SampleBundle::make()->write());

        $shared = DB::table('lookup_values')->whereNull('barangay_id')->where('type', LookupType::Religion->value)->where('label', 'Roman Catholic')->value('id');
        $this->assertSame($shared, $this->resident($barangay, 'RES-000010')->religion_id);
        $this->assertSame($shared, $this->mapped($barangay, 'lookups', 'LK-1'));
        $this->assertSame(['Sample Fellowship Church'], DB::table('lookup_values')->where('barangay_id', $barangay->id)->where('type', LookupType::Religion->value)->pluck('label')->all());
        $custom = DB::table('lookup_values')->where('barangay_id', $barangay->id)->where('label', 'Sample Fellowship Church')->sole();
        $this->assertSame(5, (int) $custom->sort_order);
        $pedro = DB::table('residents')->where('barangay_id', $barangay->id)->where('first_name', 'Pedro')->sole();
        $this->assertSame($custom->id, $pedro->religion_id);
        $this->assertNull(DB::table('lookup_values')->whereNull('barangay_id')->where('label', 'Sample Fellowship Church')->first());
    }

    public function test_rerunning_the_import_adds_nothing(): void
    {
        $barangay = $this->onboardedBarangay();
        $path = SampleBundle::make()->write();
        $first = $this->importBundle($barangay, $path);
        $before = $this->tenantCounts($barangay);

        $second = $this->importBundle($barangay, $path);

        $this->assertSame(0, $second->totals['imported']);
        $this->assertSame($first->totals['errors'], $second->totals['errors']);
        $this->assertSame($first->entities['residents']['imported'], $second->entities['residents']['problems']['already_imported'] ?? 0);
        $after = $this->tenantCounts($barangay);
        unset($before['import_runs'], $after['import_runs'], $before['audit_events'], $after['audit_events']);
        $this->assertSame($before, $after);
        $this->assertSame(2, ImportRun::acrossBarangays()->where('barangay_id', $barangay->id)->count());
    }

    public function test_dry_run_validates_and_reports_but_writes_nothing(): void
    {
        $barangay = $this->onboardedBarangay();
        $before = $this->tenantCounts($barangay);

        $report = $this->importBundle($barangay, SampleBundle::make()->write(), new ImportOptions(BundleEntity::cases(), dryRun: true));

        $this->assertTrue($report->dryRun);
        $this->assertNull($report->runId);
        $this->assertSame(3, $report->entities['residents']['imported']);
        $this->assertSame($before, $this->tenantCounts($barangay));
        $this->assertSame([$report->problemsPath], Storage::disk('local')->allFiles());
        $this->assertStringContainsString('dry-run', (string) $report->problemsPath);
    }

    public function test_audit_rows_record_the_run_with_counts_only(): void
    {
        $barangay = $this->onboardedBarangay();

        $this->importBundle($barangay, SampleBundle::make()->write());

        $events = DB::table('audit_events')->where('barangay_id', $barangay->id)
            ->whereIn('action', [AuditAction::DataImportStarted->value, AuditAction::DataImportFinished->value])
            ->orderBy('id')->get();
        $this->assertSame([AuditAction::DataImportStarted->value, AuditAction::DataImportFinished->value], $events->pluck('action')->all());
        $payload = (string) $events->pluck('changes')->implode(' ');
        $this->assertStringContainsString('"imported"', $payload);
        foreach (['Juan', 'Sample', '0917', 'OR-', 'R-1'] as $value) {
            $this->assertStringNotContainsString($value, $payload);
        }
    }

    public function test_or_number_conflicts_keep_the_first_and_flag_the_rest(): void
    {
        $barangay = $this->onboardedBarangay();

        $report = $this->importBundle($barangay, SampleBundle::make()->write());

        $first = DB::table('barangay_clearances')->where('barangay_id', $barangay->id)->where('control_number', 'BCC-2024-000007')->sole();
        $second = DB::table('barangay_clearances')->where('barangay_id', $barangay->id)->where('control_number', 'BCC-2023-000012')->sole();
        $this->assertSame('OR-200', $first->or_number);
        $this->assertNull($second->or_number);
        $this->assertStringContainsString('OR-200', (string) $second->import_note);
        $this->assertSame(1, $report->entities['barangay_clearances']['problems']['or_number_conflict']);
        $this->assertSame(1, $report->entities['other_collections']['problems']['or_number_conflict']);
        $this->assertSame(0, DB::table('other_collections')->where('barangay_id', $barangay->id)->where('or_number', 'OR-100')->count());
        $this->assertSame(1, DB::table('official_receipts')->where('barangay_id', $barangay->id)->where('or_number', 'OR-100')->whereNull('cancelled_at')->count());
        $this->assertSame(1, DB::table('official_receipts')->where('barangay_id', $barangay->id)->where('or_number', 'OR-100')->whereNotNull('cancelled_at')->count());
        $this->assertSame(['OR-100', 'OR-100', 'OR-200', 'OR-300', 'OR-400', 'OR-500', 'OR-600'], DB::table('official_receipts')->where('barangay_id', $barangay->id)->orderBy('or_number')->pluck('or_number')->all());
    }

    public function test_sequences_continue_after_the_highest_imported_number(): void
    {
        $barangay = $this->onboardedBarangay();
        $this->importBundle($barangay, SampleBundle::make()->write());

        $allocator = app(DocumentNumberAllocator::class);
        $next = app(CurrentBarangay::class)->runAs($barangay, fn (): array => [
            $allocator->next(DocumentKind::BarangayClearanceControl, 2024),
            $allocator->next(DocumentKind::BarangayClearanceControl, 2023),
            $allocator->next(DocumentKind::Resident, 2026),
            $allocator->next(DocumentKind::DisbursementVoucher, 2024),
            $allocator->next(DocumentKind::Cedula, 2024),
            $allocator->next(DocumentKind::BarangayClearanceControl, 2025),
        ]);

        $this->assertSame(['BCC-2024-000008', 'BCC-2023-000013', 'RES-000012', 'DV-2024-000007', 'CTC-2024-000032', 'BCC-2025-000001'], $next);
    }

    public function test_users_are_inactive_without_a_usable_password_and_get_their_role(): void
    {
        $taken = $this->onboardedBarangay();
        $this->barangayUser(SystemRole::Clerk, $taken)->forceFill(['username' => 'sample.clerk'])->save();
        $barangay = $this->onboardedBarangay();

        $report = $this->importBundle($barangay, SampleBundle::make()->write());

        $users = DB::table('users')->where('barangay_id', $barangay->id)->orderBy('username')->get();
        $this->assertSame(['sample.admin', 'sample.clerk-'.$barangay->psgc_code], $users->pluck('username')->all());
        foreach ($users as $user) {
            $this->assertFalse((bool) $user->is_active);
            $this->assertTrue(Hash::isHashed($user->password));
            $this->assertFalse(Hash::check('', $user->password));
        }
        $this->assertSame(1, $report->entities['users']['problems']['username_renamed']);

        $roles = DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')->join('users', 'users.id', '=', 'user_roles.user_id')
            ->where('users.barangay_id', $barangay->id)->where('user_roles.barangay_id', $barangay->id)->pluck('roles.key', 'users.username')->all();
        $this->assertSame(SystemRole::BarangayAdmin->value, $roles['sample.admin']);
        $this->assertSame(SystemRole::Clerk->value, $roles['sample.clerk-'.$barangay->psgc_code]);
    }

    public function test_color_tags_are_skipped_while_the_flag_is_off(): void
    {
        $barangay = $this->onboardedBarangay();

        $report = $this->importBundle($barangay, SampleBundle::make()->write());

        $this->assertSame(0, $this->rows('color_categories', $barangay));
        $this->assertSame(0, $this->rows('resident_political_tags', $barangay));
        $this->assertSame(1, $report->entities['color_categories']['problems']['color_tag_skipped']);
        $this->assertSame(2, $report->entities['residents']['problems']['color_tag_skipped']);
    }

    public function test_color_tags_are_imported_while_the_flag_is_on(): void
    {
        $barangay = $this->onboardedBarangay();
        app(FeatureFlags::class)->override(FeatureFlag::PoliticalColorTagging, $barangay, true, null, null);

        $report = $this->importBundle($barangay, SampleBundle::make()->write());

        $category = DB::table('color_categories')->where('barangay_id', $barangay->id)->sole();
        $this->assertSame('Blue Group', $category->name);
        $this->assertSame('#2563eb', $category->color_hex);
        $this->assertSame(2, $this->rows('resident_political_tags', $barangay));
        $this->assertArrayNotHasKey('color_tag_skipped', $report->entities['residents']['problems']);
    }

    public function test_attachments_are_stored_on_the_private_disk(): void
    {
        $barangay = $this->onboardedBarangay();

        $this->importBundle($barangay, SampleBundle::make()->write());

        $juan = $this->resident($barangay, 'RES-000010');
        $this->assertStringStartsWith("barangays/{$barangay->id}/residents/{$juan->id}/photo-", $juan->photo_path);
        $this->assertStringEndsWith('.png', $juan->photo_path);
        Storage::disk('local')->assertExists($juan->photo_path);
        $this->assertSame("\x89PNG", substr((string) Storage::disk('local')->get($juan->photo_path), 0, 4));
        $this->assertSame('2019-03-05 01:30:00', $juan->photo_taken_at);
        $photo = DB::table('blotter_photos')->where('barangay_id', $barangay->id)->sole();
        $this->assertSame('image/jpeg', $photo->mime_type);
        Storage::disk('local')->assertExists($photo->path);
    }

    public function test_missing_or_unsupported_attachments_are_warnings(): void
    {
        $barangay = $this->onboardedBarangay();
        $path = SampleBundle::make()
            ->file('residents/r-1.png', 'not an image')
            ->change('blotters', 'BL-1', fn (array $record): array => [...$record, 'photos' => ['blotters/missing.jpg']])
            ->write();

        $report = $this->importBundle($barangay, $path);

        $this->assertSame(1, $report->entities['residents']['problems']['attachment_unsupported']);
        $this->assertSame(1, $report->entities['blotters']['problems']['attachment_missing']);
        $this->assertNull($this->resident($barangay, 'RES-000010')->photo_path);
        $this->assertSame(0, $this->rows('blotter_photos', $barangay));
    }

    public function test_pdf_templates_store_the_file_and_mappings(): void
    {
        $barangay = $this->onboardedBarangay();
        $path = SampleBundle::make()
            ->file('templates/permit.pdf', FillablePdf::make(['name']))
            ->add('form_templates', ['source_key' => 'FT-2', 'name' => 'Permit', 'type' => 'Certificates', 'kind' => 'pdf', 'versions' => [
                ['version' => 1, 'pdf_file' => 'templates/permit.pdf', 'pdf_mappings' => [['pdf_field' => 'name', 'pdf_field_type' => 'text', 'token' => 'resident.full_name']]],
            ]])
            ->add('form_templates', ['source_key' => 'FT-3', 'name' => 'Broken', 'type' => 'Certificates', 'kind' => 'pdf', 'versions' => [
                ['version' => 1, 'pdf_file' => 'templates/missing.pdf'],
            ]])
            ->write();

        $report = $this->importBundle($barangay, $path);

        $this->assertSame(1, $report->entities['form_templates']['problems']['attachment_missing']);
        $template = DB::table('form_templates')->where('barangay_id', $barangay->id)->where('name', 'Permit')->sole();
        $version = DB::table('form_template_versions')->where('id', $template->current_version_id)->sole();
        $this->assertSame('permit.pdf', $version->pdf_file_name);
        Storage::disk('local')->assertExists($version->pdf_path);
        $this->assertSame(hash('sha256', (string) Storage::disk('local')->get($version->pdf_path)), $version->pdf_sha256);
        $this->assertSame('resident.full_name', DB::table('form_pdf_mappings')->where('form_template_version_id', $version->id)->value('token'));
        $this->assertNull(DB::table('form_templates')->where('barangay_id', $barangay->id)->where('name', 'Broken')->first());
    }

    public function test_dangling_references_are_reported(): void
    {
        $barangay = $this->onboardedBarangay();

        $report = $this->importBundle($barangay, SampleBundle::make()->write());

        $this->assertSame(1, $report->entities['family_members']['problems']['dangling_reference']);
        $this->assertSame(1, $report->entities['residents']['problems']['dangling_reference']);
        $this->assertNull(DB::table('residents')->where('barangay_id', $barangay->id)->where('first_name', 'Pedro')->value('purok_id'));
        $csv = (string) Storage::disk('local')->get((string) $report->problemsPath);
        $this->assertStringContainsString('family_members,F-2,dangling_reference', $csv);
        $this->assertStringContainsString('residents,R-3,dangling_reference', $csv);
    }

    public function test_importing_into_one_barangay_never_touches_another(): void
    {
        $first = $this->onboardedBarangay();
        $second = $this->onboardedBarangay();
        $path = SampleBundle::make()->write();
        $before = $this->tenantCounts($second);

        $this->importBundle($first, $path);

        $this->assertSame($before, $this->tenantCounts($second));

        $report = $this->importBundle($second, $path);

        $this->assertSame(3, $report->entities['residents']['imported']);
        $this->assertSame(3, $this->rows('residents', $first));
        $this->assertSame(3, $this->rows('residents', $second));
        $this->assertSame(
            DB::table('import_id_map')->where('barangay_id', $first->id)->count(),
            DB::table('import_id_map')->where('barangay_id', $second->id)->count(),
        );
        $this->assertSame(0, DB::table('import_id_map as m')->join('residents as r', 'r.id', '=', 'm.target_id')
            ->where('m.target_table', 'residents')->whereColumn('m.barangay_id', '<>', 'r.barangay_id')->count());
        $this->assertSame('sample.admin-'.$second->psgc_code, DB::table('users')->where('barangay_id', $second->id)->orderBy('id')->value('username'));
    }

    public function test_problem_csv_lists_entity_key_and_code_only(): void
    {
        $barangay = $this->onboardedBarangay();

        $report = $this->importBundle($barangay, SampleBundle::make()->write());

        $csv = (string) Storage::disk('local')->get((string) $report->problemsPath);
        $lines = array_values(array_filter(explode("\n", $csv)));
        $this->assertSame('entity,source_key,problem', $lines[0]);
        $this->assertCount($report->problemRows + 1, $lines);
        foreach (array_slice($lines, 1) as $line) {
            $this->assertCount(3, str_getcsv($line, escape: ''));
        }
        $this->assertContains('barangay_clearances,BC-2,or_number_conflict', $lines);
        $this->assertContains('residents,R-4,missing_name', $lines);
        foreach (['Juan', 'Maria', 'Pedro', 'Sample', '0917', 'OR-', 'VIN-', 'Neighbor', 'CC-1'] as $value) {
            $this->assertStringNotContainsString($value, $csv);
        }
    }

    public function test_only_option_runs_selected_entities_and_resolves_earlier_runs(): void
    {
        $barangay = $this->onboardedBarangay();
        $path = SampleBundle::make()->write();

        $report = $this->importBundle($barangay, $path, new ImportOptions([BundleEntity::Puroks, BundleEntity::ColorCategories, BundleEntity::Residents]));

        $this->assertSame(['puroks', 'color_categories', 'residents'], array_keys($report->entities));
        $this->assertSame(0, $this->rows('id_cards', $barangay));

        $second = $this->importBundle($barangay, $path, new ImportOptions([BundleEntity::IdCards]));

        $this->assertSame(['id_cards'], array_keys($second->entities));
        $this->assertSame(2, $second->entities['id_cards']['imported']);
    }

    private function rows(string $table, Barangay $barangay): int
    {
        $query = DB::table($table)->where('barangay_id', $barangay->id);
        if ($table === 'residents') {
            $query->whereNull('deleted_at');
        }

        return $table === 'residents' ? DB::table($table)->where('barangay_id', $barangay->id)->count() : $query->count();
    }

    /**
     * @return array<string, int>
     */
    private function tenantCounts(Barangay $barangay): array
    {
        $counts = [];
        foreach (self::TENANT_TABLES as $table) {
            $counts[$table] = DB::table($table)->where('barangay_id', $barangay->id)->count();
        }

        return $counts;
    }

    private function resident(Barangay $barangay, string $number): object
    {
        return DB::table('residents')->where('barangay_id', $barangay->id)->where('resident_number', $number)->sole();
    }

    private function mapped(Barangay $barangay, string $entity, string $key): int
    {
        return (int) DB::table('import_id_map')->where('barangay_id', $barangay->id)->where('entity', $entity)->where('source_key', $key)->value('target_id');
    }
}
