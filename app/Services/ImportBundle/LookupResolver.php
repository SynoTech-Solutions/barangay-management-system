<?php

namespace App\Services\ImportBundle;

use App\Domain\Residents\NameNormalizer;
use App\Enums\LookupType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class LookupResolver implements ChunkAware
{
    public const string TABLE = 'lookup_values';

    /**
     * @var array<string, array<string, int>>
     */
    private array $cache = [];

    /**
     * @var array<string, array<string, int>>
     */
    private array $pending = [];

    public function __construct(
        private readonly int $barangayId,
        private readonly CarbonImmutable $now,
    ) {}

    public function find(LookupType $type, ?string $label): ?int
    {
        $key = NameNormalizer::key($label ?? '');
        if ($key === '') {
            return null;
        }

        return $this->pending[$type->value][$key] ?? $this->load($type)[$key] ?? null;
    }

    public function findOrCreate(LookupType $type, string $label, int $sortOrder = 0, bool $active = true): int
    {
        $existing = $this->find($type, $label);
        if ($existing !== null) {
            return $existing;
        }

        $clean = NameNormalizer::normalize($label) ?? $label;
        $id = (int) DB::table(self::TABLE)->insertGetId([
            'barangay_id' => $this->barangayId,
            'type' => $type->value,
            'label' => $clean,
            'sort_order' => $sortOrder,
            'is_active' => $active,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        $this->pending[$type->value][NameNormalizer::key($clean)] = $id;

        return $id;
    }

    public function begin(): void
    {
        $this->pending = [];
    }

    public function commit(): void
    {
        foreach ($this->pending as $type => $entries) {
            foreach ($entries as $key => $id) {
                $this->cache[$type][(string) $key] = $id;
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
    private function load(LookupType $type): array
    {
        if (! isset($this->cache[$type->value])) {
            $values = [];
            $rows = DB::table(self::TABLE)
                ->where('type', $type->value)
                ->where(fn ($query) => $query->whereNull('barangay_id')->orWhere('barangay_id', $this->barangayId))
                ->orderByRaw('barangay_id is not null')
                ->orderBy('id')
                ->get(['id', 'label']);
            foreach ($rows as $row) {
                $values[NameNormalizer::key((string) $row->label)] ??= (int) $row->id;
            }
            $this->cache[$type->value] = $values;
        }

        return $this->cache[$type->value];
    }
}
