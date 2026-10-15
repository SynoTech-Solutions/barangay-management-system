<?php

namespace App\Console\Commands;

use App\Enums\BundleEntity;
use App\Models\Barangay;
use App\Models\User;
use App\Services\ImportBundle\Bundle;
use App\Services\ImportBundle\BundleImporter;
use App\Services\ImportBundle\BundleOpener;
use App\Services\ImportBundle\BundleRejected;
use App\Services\ImportBundle\BundleValidator;
use App\Services\ImportBundle\ImportOptions;
use App\Services\ImportBundle\ImportReport;
use App\Services\ImportBundle\ImportTally;
use Illuminate\Console\Command;

class ImportBundleCommand extends Command
{
    private const string LIST_SEPARATOR = ', ';

    protected $signature = 'bms:import:bundle
        {path : Directory that holds manifest.json, the entity files, and files/}
        {--barangay= : PSGC code of the onboarded barangay that receives the data (overrides the manifest)}
        {--dry-run : Validate and import inside a transaction that is rolled back; write nothing except the problem report}
        {--only= : Comma-separated entities to import; default every entity in the bundle}
        {--operator= : Username of the staff member running the import, recorded on the run}';

    protected $description = 'Validate an import bundle and import it into an onboarded barangay';

    public function handle(BundleOpener $opener, BundleImporter $importer): int
    {
        $path = $this->argument('path');

        try {
            $bundle = $opener->open(is_string($path) ? $path : '');
        } catch (BundleRejected $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(__('import.command.opened', [
            'version' => $bundle->version,
            'generator' => $bundle->generatorName,
            'generator_version' => $bundle->generatorVersion,
            'generated_at' => $bundle->generatedAt,
        ]));

        $barangay = $this->barangay($bundle);
        $entities = $this->entities($bundle);
        $operator = $this->operator();
        if ($barangay === null || $entities === null || $operator === false) {
            return self::INVALID;
        }

        $options = new ImportOptions(
            entities: $entities,
            dryRun: (bool) $this->option('dry-run'),
            operatorId: $operator?->id,
            operatorName: $operator !== null ? $operator->username : (get_current_user() ?: null),
        );

        $missing = $importer->missingDependencies($barangay, $bundle, $options);
        foreach ($missing as $gap) {
            $this->components->error(__('import.command.missing_dependency', ['entity' => $gap['entity']->value, 'dependency' => $gap['dependency']->value]));
        }
        if ($missing !== []) {
            return self::INVALID;
        }

        $report = $importer->run($barangay, $bundle, $options, function (BundleEntity $entity, int $count): void {
            $this->components->info(__('import.command.progress', ['entity' => $entity->value, 'count' => $count]));
        });

        $this->render($report, $barangay);

        return $report->hasErrors() ? self::FAILURE : self::SUCCESS;
    }

    private function barangay(Bundle $bundle): ?Barangay
    {
        $flag = $this->option('barangay');
        $code = is_string($flag) && $flag !== '' ? $flag : $bundle->barangayPsgc;
        if ($code === null) {
            $this->components->error(__('import.command.barangay_required'));

            return null;
        }

        if ($bundle->barangayPsgc !== null && $bundle->barangayPsgc !== $code) {
            $this->components->warn(__('import.command.barangay_mismatch', ['manifest' => $bundle->barangayPsgc, 'flag' => $code]));
        }

        $barangay = Barangay::query()->where('psgc_code', $code)->first();
        if ($barangay === null) {
            $this->components->error(__('import.command.barangay_unknown', ['code' => $code]));
        }

        return $barangay;
    }

    /**
     * @return list<BundleEntity>|null
     */
    private function entities(Bundle $bundle): ?array
    {
        $only = $this->option('only');
        if (! is_string($only) || trim($only) === '') {
            return $bundle->present();
        }

        $selected = [];
        foreach (array_filter(array_map('trim', explode(',', $only))) as $name) {
            $entity = BundleEntity::tryFrom($name);
            if ($entity === null) {
                $this->components->error(__('import.command.unknown_entity', ['name' => $name, 'entities' => implode(self::LIST_SEPARATOR, BundleEntity::values())]));

                return null;
            }

            if (! $bundle->has($entity)) {
                $this->components->warn(__('import.command.not_in_bundle', ['entity' => $entity->value]));
            }
            $selected[] = $entity;
        }

        return array_values(array_filter(BundleEntity::cases(), fn (BundleEntity $entity): bool => in_array($entity, $selected, true)));
    }

    private function operator(): User|false|null
    {
        $name = $this->option('operator');
        if (! is_string($name) || $name === '') {
            return null;
        }

        $operator = User::query()->whereRaw('lower(username) = lower(?)', [$name])->first();
        if ($operator === null) {
            $this->components->error(__('import.command.operator_unknown', ['username' => $name]));

            return false;
        }

        return $operator;
    }

    private function render(ImportReport $report, Barangay $barangay): void
    {
        foreach (array_slice($report->schemaErrors, 0, BundleValidator::DETAIL_LIMIT) as $error) {
            $this->components->error(__('import.command.schema_error', $error));
        }
        if (count($report->schemaErrors) > BundleValidator::DETAIL_LIMIT) {
            $this->components->error(__('import.command.more_errors', ['count' => count($report->schemaErrors) - BundleValidator::DETAIL_LIMIT]));
        }

        $rows = [];
        foreach ($report->entities as $entity => $counts) {
            $rows[] = [$entity, $counts[ImportTally::READ], $counts[ImportTally::IMPORTED], $counts[ImportTally::SKIPPED], $counts[ImportTally::WARNINGS], $counts[ImportTally::ERRORS]];
        }
        $rows[] = ['total', ...array_values($report->totals)];
        $this->table(['Entity', 'Read', 'Imported', 'Skipped', 'Warnings', 'Errors'], $rows);

        $problems = $report->problemTotals();
        if ($problems !== []) {
            $this->table(['Problem', 'Count'], array_map(fn (string $code, int $count): array => [$code, $count], array_keys($problems), $problems));
        }

        $this->components->info(__('import.command.problems', ['rows' => $report->problemRows, 'path' => (string) $report->problemsPath]));

        if ($report->rejected()) {
            $this->components->error(__('import.command.rejected', ['count' => $report->totals[ImportTally::ERRORS]]));

            return;
        }

        $this->components->info(__('import.command.sequences', ['count' => count($report->sequences)]));
        if ($report->dryRun) {
            $this->components->warn(__('import.command.dry_run', ['barangay' => $barangay->name]));
        } else {
            $this->components->info(__('import.command.finished', ['run' => $report->runId, 'barangay' => $barangay->name, 'status' => $report->status->value]));
        }
    }
}
