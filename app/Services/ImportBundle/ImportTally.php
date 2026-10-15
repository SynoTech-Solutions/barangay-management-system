<?php

namespace App\Services\ImportBundle;

use App\Enums\ImportProblem;

final class ImportTally implements ChunkAware
{
    public const string READ = 'read';

    public const string IMPORTED = 'imported';

    public const string SKIPPED = 'skipped';

    public const string WARNINGS = 'warnings';

    public const string ERRORS = 'errors';

    public const string PROBLEMS = 'problems';

    public const array COLUMNS = [self::READ, self::IMPORTED, self::SKIPPED, self::WARNINGS, self::ERRORS];

    public const string SEVERITY_SKIPPED = 'skipped';

    public const string SEVERITY_WARNING = 'warning';

    public const string SEVERITY_ERROR = 'error';

    private const array QUIET = [ImportProblem::AlreadyImported];

    /**
     * @var array<string, array{read: int, imported: int, skipped: int, warnings: int, errors: int, problems: array<string, int>}>
     */
    private array $entities = [];

    /**
     * @var array<string, array{read: int, imported: int, skipped: int, warnings: int, errors: int, problems: array<string, int>}>
     */
    private array $mark = [];

    /**
     * @var list<array{entity: string, key: string, code: string}>
     */
    private array $problems = [];

    private int $problemMark = 0;

    public function start(string $entity): void
    {
        $this->entities[$entity] ??= self::empty();
    }

    public function read(string $entity, int $count): void
    {
        $this->start($entity);
        $this->entities[$entity][self::READ] += $count;
    }

    public function imported(string $entity, int $count = 1): void
    {
        $this->start($entity);
        $this->entities[$entity][self::IMPORTED] += $count;
    }

    public function record(string $entity, string $severity, string $key, ImportProblem $problem): void
    {
        $this->start($entity);
        $column = match ($severity) {
            self::SEVERITY_SKIPPED => self::SKIPPED,
            self::SEVERITY_ERROR => self::ERRORS,
            default => self::WARNINGS,
        };
        $counts = $this->entities[$entity];
        $counts[$column]++;
        $counts[self::PROBLEMS][$problem->value] = ($counts[self::PROBLEMS][$problem->value] ?? 0) + 1;
        $this->entities[$entity] = $counts;

        if (! in_array($problem, self::QUIET, true)) {
            $this->problems[] = ['entity' => $entity, 'key' => $key, 'code' => $problem->value];
        }
    }

    /**
     * @return array<string, array{read: int, imported: int, skipped: int, warnings: int, errors: int, problems: array<string, int>}>
     */
    public function entities(): array
    {
        return $this->entities;
    }

    /**
     * @return list<array{entity: string, key: string, code: string}>
     */
    public function problems(): array
    {
        return $this->problems;
    }

    /**
     * @return array<string, int>
     */
    public function totals(): array
    {
        $totals = array_fill_keys(self::COLUMNS, 0);
        foreach ($this->entities as $counts) {
            foreach (self::COLUMNS as $column) {
                $totals[$column] += $counts[$column];
            }
        }

        return $totals;
    }

    public function begin(): void
    {
        $this->mark = $this->entities;
        $this->problemMark = count($this->problems);
    }

    public function commit(): void
    {
        $this->begin();
    }

    public function rollback(): void
    {
        foreach ($this->entities as $entity => $counts) {
            $read = $counts[self::READ];
            $this->entities[$entity] = $this->mark[$entity] ?? self::empty();
            $this->entities[$entity][self::READ] = $read;
        }
        array_splice($this->problems, $this->problemMark);
    }

    /**
     * @return array{read: int, imported: int, skipped: int, warnings: int, errors: int, problems: array<string, int>}
     */
    private static function empty(): array
    {
        return [self::READ => 0, self::IMPORTED => 0, self::SKIPPED => 0, self::WARNINGS => 0, self::ERRORS => 0, self::PROBLEMS => []];
    }
}
