<?php

namespace Tests\Feature\ImportBundle;

use App\Enums\SystemRole;
use App\Models\ImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ImportsBundles;
use Tests\Support\BundleWriter;
use Tests\Support\SampleBundle;
use Tests\TestCase;

class BundleImportCommandTest extends TestCase
{
    use ImportsBundles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBundleImports();
    }

    public function test_command_imports_and_prints_the_summary(): void
    {
        $barangay = $this->onboardedBarangay();
        $operator = $this->barangayUser(SystemRole::BarangayAdmin, $barangay);
        $path = SampleBundle::make()->write();

        $this->artisan('bms:import:bundle', ['path' => $path, '--barangay' => $barangay->psgc_code, '--only' => 'residents,puroks,color_categories', '--operator' => $operator->username])
            ->expectsOutputToContain('Bundle format v1 from test-writer 0.0.1')
            ->expectsOutputToContain('residents: 4 record(s)')
            ->expectsTable(['Entity', 'Read', 'Imported', 'Skipped', 'Warnings', 'Errors'], [
                ['puroks', 2, 2, 0, 0, 0],
                ['color_categories', 1, 0, 1, 0, 0],
                ['residents', 4, 3, 0, 3, 1],
                ['total', 7, 5, 1, 3, 1],
            ])
            ->assertExitCode(1);

        $this->assertSame(3, DB::table('residents')->where('barangay_id', $barangay->id)->count());
        $this->assertSame($operator->id, ImportRun::acrossBarangays()->sole()->operator_id);
    }

    public function test_a_clean_bundle_exits_zero_and_takes_the_barangay_from_the_manifest(): void
    {
        $barangay = $this->onboardedBarangay();
        $path = BundleWriter::make()
            ->with('puroks', [['source_key' => 'P-1', 'name' => 'Purok Isa']])
            ->manifest(['barangay_psgc' => $barangay->psgc_code])
            ->write();

        $this->artisan('bms:import:bundle', ['path' => $path])->assertExitCode(0);

        $this->assertSame(1, DB::table('puroks')->where('barangay_id', $barangay->id)->count());
    }

    public function test_the_barangay_flag_wins_over_the_manifest(): void
    {
        $manifestBarangay = $this->onboardedBarangay();
        $barangay = $this->onboardedBarangay();
        $path = BundleWriter::make()
            ->with('puroks', [['source_key' => 'P-1', 'name' => 'Purok Isa']])
            ->manifest(['barangay_psgc' => $manifestBarangay->psgc_code])
            ->write();

        $this->artisan('bms:import:bundle', ['path' => $path, '--barangay' => $barangay->psgc_code])
            ->expectsOutputToContain('because --barangay wins')
            ->assertExitCode(0);

        $this->assertSame(1, DB::table('puroks')->where('barangay_id', $barangay->id)->count());
        $this->assertSame(0, DB::table('puroks')->where('barangay_id', $manifestBarangay->id)->count());
    }

    public function test_dry_run_from_the_command_writes_nothing(): void
    {
        $barangay = $this->onboardedBarangay();

        $this->artisan('bms:import:bundle', ['path' => SampleBundle::make()->write(), '--barangay' => $barangay->psgc_code, '--dry-run' => true])
            ->expectsOutputToContain('nothing was written except the problem report')
            ->assertExitCode(1);

        $this->assertSame(0, DB::table('residents')->where('barangay_id', $barangay->id)->count());
        $this->assertSame(0, ImportRun::acrossBarangays()->count());
    }

    public function test_invalid_options_exit_two(): void
    {
        $barangay = $this->onboardedBarangay();
        $path = SampleBundle::make()->write();

        $this->artisan('bms:import:bundle', ['path' => $path])->expectsOutputToContain('Pass --barangay')->assertExitCode(2);
        $this->artisan('bms:import:bundle', ['path' => $path, '--barangay' => '0000000000'])->assertExitCode(2);
        $this->artisan('bms:import:bundle', ['path' => $path, '--barangay' => $barangay->psgc_code, '--only' => 'nope'])
            ->expectsOutputToContain('Unknown entity "nope"')
            ->assertExitCode(2);
        $this->artisan('bms:import:bundle', ['path' => $path, '--barangay' => $barangay->psgc_code, '--operator' => 'nobody'])->assertExitCode(2);
    }

    public function test_only_refuses_entities_whose_dependencies_were_never_imported(): void
    {
        $barangay = $this->onboardedBarangay();
        $path = SampleBundle::make()->write();

        $this->artisan('bms:import:bundle', ['path' => $path, '--barangay' => $barangay->psgc_code, '--only' => 'id_cards'])
            ->expectsOutputToContain('id_cards depends on residents, which is in the bundle but neither selected nor imported by an earlier run')
            ->assertExitCode(2);
        $this->assertSame(0, ImportRun::acrossBarangays()->count());

        $this->artisan('bms:import:bundle', ['path' => $path, '--barangay' => $barangay->psgc_code, '--only' => 'residents,puroks,color_categories'])->assertExitCode(1);
        $this->artisan('bms:import:bundle', ['path' => $path, '--barangay' => $barangay->psgc_code, '--only' => 'id_cards'])->assertExitCode(0);

        $this->assertSame(2, DB::table('id_cards')->where('barangay_id', $barangay->id)->count());
    }

    public function test_a_rejected_bundle_exits_one_and_lists_schema_errors_without_values(): void
    {
        $barangay = $this->onboardedBarangay();
        $path = SampleBundle::make()->change('residents', 'R-1', fn (array $record): array => [...$record, 'birthdate' => '15/01/1960'])->write();

        $this->artisan('bms:import:bundle', ['path' => $path, '--barangay' => $barangay->psgc_code])
            ->expectsOutputToContain('residents R-1 /birthdate')
            ->doesntExpectOutputToContain('15/01/1960')
            ->expectsOutputToContain('Bundle rejected')
            ->assertExitCode(1);

        $this->assertSame(0, DB::table('residents')->where('barangay_id', $barangay->id)->count());
    }

    public function test_a_version_mismatch_exits_one_with_a_clear_error(): void
    {
        $barangay = $this->onboardedBarangay();
        $path = SampleBundle::make()->manifest(['version' => 2])->write();

        $this->artisan('bms:import:bundle', ['path' => $path, '--barangay' => $barangay->psgc_code])
            ->expectsOutputToContain('Bundle format version 2 is not supported; this importer reads version 1.')
            ->assertExitCode(1);
    }
}
