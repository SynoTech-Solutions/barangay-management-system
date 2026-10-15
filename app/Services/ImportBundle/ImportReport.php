<?php

namespace App\Services\ImportBundle;

use App\Enums\ImportRunStatus;

final readonly class ImportReport
{
    public const int VERSION = 1;

    /**
     * @param  array<string, array{read: int, imported: int, skipped: int, warnings: int, errors: int, problems: array<string, int>}>  $entities
     * @param  array<string, int>  $totals
     * @param  array<string, int>  $sequences
     * @param  list<array{entity: string, key: string, pointer: string, keyword: string}>  $schemaErrors
     */
    public function __construct(
        public ?int $runId,
        public bool $dryRun,
        public ImportRunStatus $status,
        public array $entities,
        public array $totals,
        public array $sequences,
        public ?string $problemsPath,
        public int $problemRows,
        public array $schemaErrors = [],
    ) {}

    public function rejected(): bool
    {
        return $this->status === ImportRunStatus::Rejected;
    }

    public function hasErrors(): bool
    {
        return $this->rejected() || $this->totals[ImportTally::ERRORS] > 0;
    }

    /**
     * @return array<string, int>
     */
    public function problemTotals(): array
    {
        $totals = [];
        foreach ($this->entities as $counts) {
            foreach ($counts['problems'] as $code => $count) {
                $totals[$code] = ($totals[$code] ?? 0) + $count;
            }
        }
        ksort($totals);

        return $totals;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'v' => self::VERSION,
            'status' => $this->status->value,
            'dry_run' => $this->dryRun,
            'entities' => $this->entities,
            'totals' => $this->totals,
            'sequences' => $this->sequences,
            'problem_rows' => $this->problemRows,
        ];
    }
}
