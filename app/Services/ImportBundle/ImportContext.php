<?php

namespace App\Services\ImportBundle;

use App\Enums\BundleEntity;
use App\Enums\ImportProblem;
use App\Models\Barangay;
use Carbon\CarbonImmutable;

final class ImportContext
{
    public const string TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    private const string UTC = 'UTC';

    public BundleEntity $entity = BundleEntity::Lookups;

    public readonly IdMap $ids;

    public readonly LookupResolver $lookups;

    public readonly ReceiptBook $receipts;

    public readonly SequenceTracker $sequences;

    public readonly FileJournal $files;

    public readonly UniqueValues $unique;

    public readonly ImportTally $tally;

    public readonly string $timestamp;

    /**
     * @var list<ChunkAware>
     */
    private readonly array $components;

    public function __construct(
        public readonly Barangay $barangay,
        public readonly Bundle $bundle,
        public readonly ImportOptions $options,
        public readonly CarbonImmutable $now,
        public readonly bool $colorTagsEnabled,
        ?int $runId,
        ImportTally $tally,
    ) {
        $this->ids = new IdMap($barangay->id, $runId, $now);
        $this->lookups = new LookupResolver($barangay->id, $now);
        $this->receipts = new ReceiptBook($barangay->id, $now);
        $this->sequences = new SequenceTracker;
        $this->files = new FileJournal($options->dryRun);
        $this->unique = new UniqueValues($barangay->id);
        $this->tally = $tally;
        $this->timestamp = $now->utc()->format(self::TIMESTAMP_FORMAT);
        $this->components = [$this->ids, $this->lookups, $this->receipts, $this->sequences, $this->files, $this->unique, $this->tally];
    }

    public function barangayId(): int
    {
        return $this->barangay->id;
    }

    public function imported(): void
    {
        $this->tally->imported($this->entity->value);
    }

    public function skipped(string $key, ImportProblem $problem): void
    {
        $this->tally->record($this->entity->value, ImportTally::SEVERITY_SKIPPED, $key, $problem);
    }

    public function warn(string $key, ImportProblem $problem): void
    {
        $this->tally->record($this->entity->value, ImportTally::SEVERITY_WARNING, $key, $problem);
    }

    public function failed(string $key, ImportProblem $problem): void
    {
        $this->tally->record($this->entity->value, ImportTally::SEVERITY_ERROR, $key, $problem);
    }

    public function utc(?string $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value)->setTimezone(self::UTC)->format(self::TIMESTAMP_FORMAT);
    }

    public function utcOrNow(?string $value): string
    {
        return $this->utc($value) ?? $this->timestamp;
    }

    public function local(?string $value): CarbonImmutable
    {
        return $value === null ? $this->now : CarbonImmutable::parse($value);
    }

    public function year(?string $value): int
    {
        return $this->local($value)->year;
    }

    public function today(): string
    {
        return $this->now->toDateString();
    }

    public function begin(): void
    {
        foreach ($this->components as $component) {
            $component->begin();
        }
    }

    public function flush(): void
    {
        $this->ids->flush();
        $this->receipts->flush();
    }

    public function commit(): void
    {
        foreach ($this->components as $component) {
            $component->commit();
        }
    }

    public function rollback(): void
    {
        foreach ($this->components as $component) {
            $component->rollback();
        }
    }
}
