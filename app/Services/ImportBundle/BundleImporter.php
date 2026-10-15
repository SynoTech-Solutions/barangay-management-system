<?php

namespace App\Services\ImportBundle;

use App\Domain\Reports\CsvCell;
use App\Enums\AuditAction;
use App\Enums\BundleEntity;
use App\Enums\FeatureFlag;
use App\Enums\ImportProblem;
use App\Enums\ImportRunStatus;
use App\Enums\LogEvent;
use App\Models\Barangay;
use App\Models\ImportRun;
use App\Services\Audit\AuditTrail;
use App\Services\Features\FeatureFlags;
use App\Support\StoragePaths;
use App\Tenancy\CurrentBarangay;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final readonly class BundleImporter
{
    public const array CSV_HEADER = ['entity', 'source_key', 'problem'];

    private const string STAMP_FORMAT = 'Ymd-His';

    private const string DRY_RUN_PREFIX = 'dry-run-';

    private const int TOKEN_LENGTH = 8;

    public function __construct(
        private ImporterCatalog $catalog,
        private BundleValidator $validator,
        private CurrentBarangay $currentBarangay,
        private FeatureFlags $features,
        private AuditTrail $audit,
    ) {}

    /**
     * @param  (Closure(BundleEntity, int): void)|null  $progress
     */
    public function run(Barangay $barangay, Bundle $bundle, ImportOptions $options, ?Closure $progress = null): ImportReport
    {
        $now = CarbonImmutable::now();
        $validation = new ImportTally;
        $details = $this->validator->validate($bundle, $validation);
        if ($validation->totals()[ImportTally::ERRORS] > 0) {
            $problems = $validation->problems();

            return new ImportReport(
                runId: null,
                dryRun: $options->dryRun,
                status: ImportRunStatus::Rejected,
                entities: $validation->entities(),
                totals: $validation->totals(),
                sequences: [],
                problemsPath: $this->writeProblems($barangay->id, $problems, $now, $options->dryRun),
                problemRows: count($problems),
                schemaErrors: $details,
            );
        }

        return $this->currentBarangay->runAs($barangay, function () use ($barangay, $bundle, $options, $progress, $now): ImportReport {
            if (! $options->dryRun) {
                return $this->execute($barangay, $bundle, $options, $progress, $now);
            }

            DB::beginTransaction();

            try {
                return $this->execute($barangay, $bundle, $options, $progress, $now);
            } finally {
                DB::rollBack();
            }
        });
    }

    /**
     * @return list<array{entity: BundleEntity, dependency: BundleEntity}>
     */
    public function missingDependencies(Barangay $barangay, Bundle $bundle, ImportOptions $options): array
    {
        $completed = $this->currentBarangay->runAs($barangay, function (): array {
            $names = [];
            foreach (ImportRun::query()->where('status', ImportRunStatus::Completed)->get(['options']) as $run) {
                foreach ((array) ($run->options['entities'] ?? []) as $name) {
                    if (is_string($name)) {
                        $names[$name] = true;
                    }
                }
            }

            return $names;
        });

        $missing = [];
        foreach ($bundle->present() as $entity) {
            if (! $options->includes($entity)) {
                continue;
            }

            foreach ($entity->dependencies() as $dependency) {
                if ($bundle->has($dependency) && ! $options->includes($dependency) && ! isset($completed[$dependency->value])) {
                    $missing[] = ['entity' => $entity, 'dependency' => $dependency];
                }
            }
        }

        return $missing;
    }

    /**
     * @param  (Closure(BundleEntity, int): void)|null  $progress
     */
    private function execute(Barangay $barangay, Bundle $bundle, ImportOptions $options, ?Closure $progress, CarbonImmutable $now): ImportReport
    {
        $run = $options->dryRun ? null : DB::transaction(function () use ($barangay, $bundle, $options, $now): ImportRun {
            $run = new ImportRun([
                'status' => ImportRunStatus::Running,
                'options' => $options->toArray($bundle->describe()),
                'operator_id' => $options->operatorId,
                'operator_name' => $options->operatorName,
                'started_at' => $now,
            ]);
            $run->barangay_id = $barangay->id;
            $run->save();

            $this->audit->record(AuditAction::DataImportStarted, $run, after: [
                'entities' => $options->entityNames(),
                'bundle_version' => $bundle->version,
            ], barangayId: $barangay->id, actorId: $options->operatorId);

            return $run;
        });

        Log::info(LogEvent::DataImportStarted->value, ['barangay_id' => $barangay->id, 'run_id' => $run?->id, 'dry_run' => $options->dryRun]);

        $context = new ImportContext(
            $barangay,
            $bundle,
            $options,
            $now,
            $this->features->enabled(FeatureFlag::PoliticalColorTagging, $barangay),
            $run?->id,
            new ImportTally,
        );

        try {
            foreach ($this->catalog->importers() as $importer) {
                if ($options->includes($importer->entity()) && $bundle->has($importer->entity())) {
                    $this->importEntity($importer, $context, $progress);
                }
            }

            DB::transaction(fn (): int => $context->sequences->apply($barangay->id, $now));
            $report = $this->report($context, $run, ImportRunStatus::Completed, $now);
        } catch (Throwable $exception) {
            if ($run !== null) {
                $this->finishRun($run, $this->failedReport($context, $run, $now), $options);
            }

            throw $exception;
        }

        if ($run !== null) {
            $this->finishRun($run, $report, $options);
        }

        Log::info(LogEvent::DataImportFinished->value, ['barangay_id' => $barangay->id, 'run_id' => $run?->id, 'dry_run' => $options->dryRun, ...$report->totals]);

        return $report;
    }

    /**
     * @param  (Closure(BundleEntity, int): void)|null  $progress
     */
    private function importEntity(EntityImporter $importer, ImportContext $context, ?Closure $progress): void
    {
        $entity = $importer->entity();
        $context->entity = $entity;
        $context->tally->start($entity->value);
        if ($progress !== null) {
            $progress($entity, $context->bundle->count($entity));
        }

        foreach (JsonLines::chunks($context->bundle->file($entity), $importer->chunkSize()) as $records) {
            $context->tally->read($entity->value, count($records));
            if ($this->attempt(fn () => $importer->importChunk($records, $context), $context) === null) {
                continue;
            }

            foreach ($records as $record) {
                $error = $this->attempt(fn () => $importer->importChunk([$record], $context), $context);
                if ($error !== null) {
                    $this->recordFailed($context, $record, $error);
                }
            }
        }

        $this->attempt(fn () => $importer->finish($context), $context);
    }

    private function recordFailed(ImportContext $context, Record $record, Throwable $error): void
    {
        $context->failed($record->key(), ImportProblem::RecordFailed);
        Log::warning(LogEvent::DataImportRecordFailed->value, [
            'barangay_id' => $context->barangayId(),
            'entity' => $context->entity->value,
            'exception' => $error::class,
            'detail' => $error instanceof QueryException ? (string) ($error->errorInfo[0] ?? $error->getCode()) : class_basename($error),
            'at' => basename($error->getFile()).':'.$error->getLine(),
        ]);
    }

    private function attempt(Closure $work, ImportContext $context): ?Throwable
    {
        $context->begin();

        try {
            DB::transaction(function () use ($work, $context): void {
                $work();
                $context->flush();
            });
        } catch (Throwable $exception) {
            $context->rollback();

            return $exception;
        }

        $context->commit();

        return null;
    }

    private function report(ImportContext $context, ?ImportRun $run, ImportRunStatus $status, CarbonImmutable $now): ImportReport
    {
        $problems = $context->tally->problems();

        return new ImportReport(
            runId: $run?->id,
            dryRun: $context->options->dryRun,
            status: $status,
            entities: $context->tally->entities(),
            totals: $context->tally->totals(),
            sequences: $context->sequences->summary(),
            problemsPath: $this->writeProblems($context->barangayId(), $problems, $now, $context->options->dryRun),
            problemRows: count($problems),
        );
    }

    private function failedReport(ImportContext $context, ImportRun $run, CarbonImmutable $now): ImportReport
    {
        try {
            return $this->report($context, $run, ImportRunStatus::Failed, $now);
        } catch (Throwable) {
            return new ImportReport($run->id, false, ImportRunStatus::Failed, $context->tally->entities(), $context->tally->totals(), [], null, count($context->tally->problems()));
        }
    }

    private function finishRun(ImportRun $run, ImportReport $report, ImportOptions $options): void
    {
        DB::transaction(function () use ($run, $report, $options): void {
            $run->status = $report->status;
            $run->report = $report->toArray();
            $run->problems_path = $report->problemsPath;
            $run->finished_at = CarbonImmutable::now();
            $run->save();

            $this->audit->record(AuditAction::DataImportFinished, $run, after: [
                'status' => $report->status->value,
                'totals' => $report->totals,
                'entities' => array_map(fn (array $counts): array => array_intersect_key($counts, array_flip(ImportTally::COLUMNS)), $report->entities),
            ], barangayId: $run->barangay_id, actorId: $options->operatorId);
        });
    }

    /**
     * @param  list<array{entity: string, key: string, code: string}>  $problems
     */
    private function writeProblems(int $barangayId, array $problems, CarbonImmutable $now, bool $dryRun): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, self::CSV_HEADER, escape: '');
        foreach ($problems as $problem) {
            fputcsv($handle, array_map(CsvCell::safe(...), [$problem['entity'], $problem['key'], $problem['code']]), escape: '');
        }

        rewind($handle);
        $contents = (string) stream_get_contents($handle);
        fclose($handle);

        $token = ($dryRun ? self::DRY_RUN_PREFIX : '').Str::lower(Str::random(self::TOKEN_LENGTH));
        $path = StoragePaths::importProblems($barangayId, $now->utc()->format(self::STAMP_FORMAT), $token);
        Storage::disk(StoragePaths::PRIVATE_DISK)->put($path, $contents);

        return $path;
    }
}
