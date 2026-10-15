<?php

namespace App\Services\ImportBundle;

use App\Models\ImportIdMapping;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class IdMap implements ChunkAware
{
    /**
     * @var array<string, array<string, int>>
     */
    private array $cache = [];

    /**
     * @var array<string, array<string, array{0: string, 1: int}>>
     */
    private array $pending = [];

    public function __construct(
        private readonly int $barangayId,
        private readonly ?int $runId,
        private readonly CarbonImmutable $now,
    ) {}

    public function find(string $entity, ?string $key): ?int
    {
        if ($key === null || $key === '') {
            return null;
        }

        if (isset($this->pending[$entity][$key])) {
            return $this->pending[$entity][$key][1];
        }

        return $this->load($entity)[$key] ?? null;
    }

    public function has(string $entity, ?string $key): bool
    {
        return $this->find($entity, $key) !== null;
    }

    public function remember(string $entity, string $key, string $targetTable, int $id): void
    {
        $this->pending[$entity][$key] = [$targetTable, $id];
    }

    public function flush(): void
    {
        $rows = [];
        foreach ($this->pending as $entity => $entries) {
            foreach ($entries as $key => [$targetTable, $id]) {
                $rows[] = [
                    'barangay_id' => $this->barangayId,
                    'entity' => $entity,
                    'source_key' => (string) $key,
                    'target_table' => $targetTable,
                    'target_id' => $id,
                    'import_run_id' => $this->runId,
                    'created_at' => $this->now,
                ];
            }
        }

        TableWriter::insert(ImportIdMapping::TABLE, $rows);
    }

    public function begin(): void
    {
        $this->pending = [];
    }

    public function commit(): void
    {
        foreach ($this->pending as $entity => $entries) {
            $this->load($entity);
            foreach ($entries as $key => [, $id]) {
                $this->cache[$entity][(string) $key] = $id;
            }
        }

        $this->pending = [];
    }

    public function rollback(): void
    {
        $this->pending = [];
    }

    /**
     * @return array<string, int>
     */
    private function load(string $entity): array
    {
        if (! isset($this->cache[$entity])) {
            $this->cache[$entity] = [];
            foreach (DB::table(ImportIdMapping::TABLE)->where('barangay_id', $this->barangayId)->where('entity', $entity)->get(['source_key', 'target_id']) as $row) {
                $this->cache[$entity][(string) $row->source_key] = (int) $row->target_id;
            }
        }

        return $this->cache[$entity];
    }
}
