<?php

namespace Tests\Concerns;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Services\Access\RoleSynchronizer;
use App\Services\Barangays\BarangayOnboarding;
use App\Services\ImportBundle\BundleImporter;
use App\Services\ImportBundle\BundleOpener;
use App\Services\ImportBundle\ImportOptions;
use App\Services\ImportBundle\ImportReport;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BundleWriter;

trait ImportsBundles
{
    protected function setUpBundleImports(): void
    {
        Storage::fake('local');
        app(RoleSynchronizer::class)->sync();
        $this->seed(LookupSeeder::class);
        $this->beforeApplicationDestroyed(fn () => BundleWriter::cleanup());
    }

    protected function onboardedBarangay(?string $psgc = null): Barangay
    {
        $central = $this->centralAdmin();
        $municipality = Municipality::factory()->create();

        return app(BarangayOnboarding::class)->onboard([
            'municipality_id' => $municipality->id,
            'psgc_code' => $psgc ?? fake()->unique()->numerify('99########'),
            'name' => 'Barangay Sample '.fake()->unique()->numberBetween(1, 99999),
        ], null, $central);
    }

    protected function importBundle(Barangay $barangay, string $path, ?ImportOptions $options = null): ImportReport
    {
        $bundle = app(BundleOpener::class)->open($path);

        return app(BundleImporter::class)->run($barangay, $bundle, $options ?? new ImportOptions($bundle->present()));
    }

    /**
     * @param  array<string, int>  $expected
     */
    protected function assertEntity(ImportReport $report, string $entity, array $expected): void
    {
        $counts = $report->entities[$entity] ?? null;
        $this->assertNotNull($counts, "Entity {$entity} did not run.");
        foreach ($expected as $column => $value) {
            $this->assertSame($value, $counts[$column], "Entity {$entity} column {$column}: ".json_encode($counts));
        }
    }
}
