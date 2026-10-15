<?php

namespace Tests\Feature\ImportBundle;

use App\Enums\ImportRunStatus;
use App\Models\ImportRun;
use App\Services\ImportBundle\BundleOpener;
use App\Services\ImportBundle\BundleRejected;
use App\Services\ImportBundle\ImportReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ImportsBundles;
use Tests\Support\BundleWriter;
use Tests\Support\SampleBundle;
use Tests\TestCase;

class BundleValidationTest extends TestCase
{
    use ImportsBundles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBundleImports();
    }

    public function test_a_wrong_type_rejects_the_bundle_before_anything_is_written(): void
    {
        $barangay = $this->onboardedBarangay();
        $path = SampleBundle::make()->change('residents', 'R-1', fn (array $record): array => [...$record, 'height_cm' => 'tall'])->write();

        $report = $this->importBundle($barangay, $path);

        $this->assertRejected($report, 'residents', 'R-1', '/height_cm', 'type');
        $this->assertSame(0, DB::table('residents')->where('barangay_id', $barangay->id)->count());
        $this->assertSame(0, DB::table('lookup_values')->where('barangay_id', $barangay->id)->count());
        $this->assertSame(0, ImportRun::acrossBarangays()->count());
    }

    public function test_a_missing_required_field_is_rejected(): void
    {
        $barangay = $this->onboardedBarangay();
        $path = SampleBundle::make()->change('id_cards', 'ID-1', function (array $record): array {
            unset($record['issued_at']);

            return $record;
        })->write();

        $this->assertRejected($this->importBundle($barangay, $path), 'id_cards', 'ID-1', '/issued_at', 'required');
    }

    public function test_a_password_field_is_rejected(): void
    {
        $barangay = $this->onboardedBarangay();
        $path = SampleBundle::make()->change('users', 'U-1', fn (array $record): array => [...$record, 'password' => 'secret'])->write();

        $report = $this->importBundle($barangay, $path);

        $this->assertSame(ImportRunStatus::Rejected, $report->status);
        $this->assertSame([['entity' => 'users', 'key' => 'U-1', 'pointer' => '/password', 'keyword' => 'properties']], $report->schemaErrors);
        $this->assertSame(0, DB::table('users')->where('barangay_id', $barangay->id)->count());
    }

    public function test_attachment_paths_that_leave_the_files_folder_are_rejected(): void
    {
        $barangay = $this->onboardedBarangay();
        $path = SampleBundle::make()
            ->change('residents', 'R-1', fn (array $record): array => [...$record, 'photo' => '../manifest.json'])
            ->change('blotters', 'BL-1', fn (array $record): array => [...$record, 'photos' => ['/etc/hosts']])
            ->change('residents', 'R-2', fn (array $record): array => [...$record, 'photo' => 'residents/../../secret.png'])
            ->write();

        $report = $this->importBundle($barangay, $path);

        $this->assertSame(ImportRunStatus::Rejected, $report->status);
        $this->assertSame(2, $report->entities['residents']['problems']['schema_invalid']);
        $this->assertSame(1, $report->entities['blotters']['problems']['schema_invalid']);
        $this->assertContains(['entity' => 'residents', 'key' => 'R-1', 'pointer' => '/photo', 'keyword' => 'pattern'], $report->schemaErrors);
        $this->assertContains(['entity' => 'blotters', 'key' => 'BL-1', 'pointer' => '/photos/0', 'keyword' => 'pattern'], $report->schemaErrors);
    }

    public function test_an_unknown_entity_in_the_manifest_is_rejected(): void
    {
        $path = SampleBundle::make()->manifest(['entities' => ['pets' => ['file' => 'pets.jsonl', 'count' => 0]]])->write();

        $this->expectException(BundleRejected::class);
        $this->expectExceptionMessage('manifest.json does not match the schema: /entities');

        app(BundleOpener::class)->open($path);
    }

    public function test_an_unknown_field_is_rejected(): void
    {
        $barangay = $this->onboardedBarangay();
        $path = SampleBundle::make()->change('puroks', 'P-1', fn (array $record): array => [...$record, 'colour' => 'blue'])->write();

        $this->assertRejected($this->importBundle($barangay, $path), 'puroks', 'P-1', '/colour', 'additionalProperties');
    }

    public function test_a_different_format_version_is_rejected_with_a_clear_error(): void
    {
        $path = SampleBundle::make()->manifest(['version' => 2])->write();

        $this->expectException(BundleRejected::class);
        $this->expectExceptionMessage('Bundle format version 2 is not supported; this importer reads version 1.');

        app(BundleOpener::class)->open($path);
    }

    public function test_a_missing_manifest_is_rejected(): void
    {
        $path = SampleBundle::make()->write();
        unlink($path.'/manifest.json');

        $this->expectException(BundleRejected::class);
        $this->expectExceptionMessage('The bundle has no manifest.json.');

        app(BundleOpener::class)->open($path);
    }

    public function test_duplicate_keys_bad_json_and_wrong_counts_are_rejected(): void
    {
        $barangay = $this->onboardedBarangay();
        $path = SampleBundle::make()
            ->add('puroks', ['source_key' => 'P-1', 'name' => 'Purok Tres'])
            ->raw('sms_templates', "{\"source_key\": \"ST-1\", \"title\": \"A\", \"body\": \"B\"}\nnot json\n")
            ->manifest(['entities' => ['collection_types' => ['count' => 5]]])
            ->write();

        $report = $this->importBundle($barangay, $path);

        $this->assertSame(ImportRunStatus::Rejected, $report->status);
        $this->assertSame(1, $report->entities['puroks']['problems']['duplicate_source_key']);
        $this->assertSame(1, $report->entities['sms_templates']['problems']['invalid_json']);
        $this->assertSame(1, $report->entities['collection_types']['problems']['count_mismatch']);
        $lines = explode("\n", trim((string) Storage::disk('local')->get((string) $report->problemsPath)));
        $this->assertContains('puroks,P-1,duplicate_source_key', $lines);
        $this->assertContains('sms_templates,#2,invalid_json', $lines);
        $this->assertContains('collection_types,*,count_mismatch', $lines);
    }

    public function test_duplicate_voucher_item_keys_are_rejected(): void
    {
        $barangay = $this->onboardedBarangay();
        $item = ['source_key' => 'VI-1', 'account_key' => 'A-S1', 'description' => 'Again', 'amount' => '1.00'];
        $path = SampleBundle::make()->change('vouchers', 'VO-1', fn (array $record): array => [...$record, 'items' => [...$record['items'], $item]])->write();

        $report = $this->importBundle($barangay, $path);

        $this->assertSame(1, $report->entities['vouchers']['problems']['duplicate_source_key']);
    }

    public function test_a_minimal_bundle_with_one_entity_is_valid(): void
    {
        $barangay = $this->onboardedBarangay();
        $path = BundleWriter::make()->with('puroks', [['source_key' => 'only', 'name' => 'Purok Isa']])->write();

        $report = $this->importBundle($barangay, $path);

        $this->assertSame(ImportRunStatus::Completed, $report->status);
        $this->assertSame(['puroks'], array_keys($report->entities));
    }

    private function assertRejected(ImportReport $report, string $entity, string $key, string $pointer, string $keyword): void
    {
        $this->assertSame(ImportRunStatus::Rejected, $report->status);
        $this->assertTrue($report->hasErrors());
        $this->assertSame(1, $report->entities[$entity]['problems']['schema_invalid'] ?? 0);
        $this->assertContains(['entity' => $entity, 'key' => $key, 'pointer' => $pointer, 'keyword' => $keyword], $report->schemaErrors);
        $this->assertContains("{$entity},{$key},schema_invalid", explode("\n", trim((string) Storage::disk('local')->get((string) $report->problemsPath))));
    }
}
