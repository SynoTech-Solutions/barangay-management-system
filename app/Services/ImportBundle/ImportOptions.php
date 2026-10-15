<?php

namespace App\Services\ImportBundle;

use App\Enums\BundleEntity;

final readonly class ImportOptions
{
    public const int VERSION = 1;

    /**
     * @param  list<BundleEntity>  $entities
     */
    public function __construct(
        public array $entities,
        public bool $dryRun = false,
        public ?int $operatorId = null,
        public ?string $operatorName = null,
    ) {}

    public static function all(bool $dryRun = false): self
    {
        return new self(BundleEntity::cases(), $dryRun);
    }

    public function includes(BundleEntity $entity): bool
    {
        return in_array($entity, $this->entities, true);
    }

    /**
     * @return list<string>
     */
    public function entityNames(): array
    {
        return array_map(fn (BundleEntity $entity): string => $entity->value, $this->entities);
    }

    /**
     * @param  array<string, mixed>  $bundle
     * @return array<string, mixed>
     */
    public function toArray(array $bundle): array
    {
        return [
            'v' => self::VERSION,
            'entities' => $this->entityNames(),
            'dry_run' => $this->dryRun,
            'bundle' => $bundle,
        ];
    }
}
