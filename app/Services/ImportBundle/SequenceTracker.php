<?php

namespace App\Services\ImportBundle;

use App\Domain\ImportBundle\SourceNumber;
use App\Domain\Numbering\NumberingRule;
use App\Enums\DocumentKind;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SequenceTracker implements ChunkAware
{
    /**
     * @var array<string, array{kind: string, period: int, value: int}>
     */
    private array $committed = [];

    /**
     * @var array<string, array{kind: string, period: int, value: int}>
     */
    private array $pending = [];

    public function observe(DocumentKind $kind, ?string $number, ?int $fallbackYear): void
    {
        $parts = SourceNumber::parse($number);
        if ($parts === null || $parts->counter <= 0) {
            return;
        }

        $rule = NumberingRule::for($kind);
        $year = $parts->year ?? $fallbackYear;
        if ($rule->resetsYearly && $year === null) {
            return;
        }

        $period = $rule->period($year ?? 0);
        $slot = $kind->value.'|'.$period;
        $current = $this->pending[$slot]['value'] ?? $this->committed[$slot]['value'] ?? 0;
        if ($parts->counter > $current) {
            $this->pending[$slot] = ['kind' => $kind->value, 'period' => $period, 'value' => $parts->counter];
        }
    }

    public function apply(int $barangayId, CarbonImmutable $now): int
    {
        foreach ($this->committed as $entry) {
            DB::statement(
                'insert into document_sequences (barangay_id, kind, period, last_value, created_at, updated_at)
                 values (?, ?, ?, ?, ?, ?)
                 on conflict (barangay_id, kind, period)
                 do update set last_value = greatest(document_sequences.last_value, excluded.last_value), updated_at = excluded.updated_at',
                [$barangayId, $entry['kind'], $entry['period'], $entry['value'], $now, $now],
            );
        }

        return count($this->committed);
    }

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $summary = [];
        foreach ($this->committed as $slot => $entry) {
            $summary[$slot] = $entry['value'];
        }
        ksort($summary);

        return $summary;
    }

    public function begin(): void
    {
        $this->pending = [];
    }

    public function commit(): void
    {
        foreach ($this->pending as $slot => $entry) {
            $this->committed[$slot] = $entry;
        }
        $this->pending = [];
    }

    public function rollback(): void
    {
        $this->pending = [];
    }
}
