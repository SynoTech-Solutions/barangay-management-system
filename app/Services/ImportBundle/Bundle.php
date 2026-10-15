<?php

namespace App\Services\ImportBundle;

use App\Domain\ImportBundle\AttachmentPath;
use App\Domain\ImportBundle\BundleFormat;
use App\Enums\BundleEntity;

final readonly class Bundle
{
    /**
     * @param  array<string, array{file: string, count: int}>  $entities
     */
    public function __construct(
        public string $path,
        public int $version,
        public ?string $barangayPsgc,
        public string $generatorName,
        public string $generatorVersion,
        public string $generatedAt,
        public array $entities,
    ) {}

    public function has(BundleEntity $entity): bool
    {
        return isset($this->entities[$entity->value]);
    }

    public function file(BundleEntity $entity): string
    {
        return $this->path.DIRECTORY_SEPARATOR.($this->entities[$entity->value]['file'] ?? $entity->value);
    }

    public function count(BundleEntity $entity): int
    {
        return $this->entities[$entity->value]['count'] ?? 0;
    }

    /**
     * @return list<BundleEntity>
     */
    public function present(): array
    {
        return array_values(array_filter(BundleEntity::cases(), fn (BundleEntity $entity): bool => $this->has($entity)));
    }

    public function attachment(string $relative): ?string
    {
        if (! AttachmentPath::isSafe($relative)) {
            return null;
        }

        $root = realpath($this->path.DIRECTORY_SEPARATOR.BundleFormat::FILES_DIRECTORY);
        $file = realpath($this->path.DIRECTORY_SEPARATOR.BundleFormat::FILES_DIRECTORY.DIRECTORY_SEPARATOR.$relative);
        if ($root === false || $file === false || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR) || ! is_file($file)) {
            return null;
        }

        return $file;
    }

    /**
     * @return array{format: string, version: int, generator: string, generator_version: string, generated_at: string}
     */
    public function describe(): array
    {
        return [
            'format' => BundleFormat::NAME,
            'version' => $this->version,
            'generator' => $this->generatorName,
            'generator_version' => $this->generatorVersion,
            'generated_at' => $this->generatedAt,
        ];
    }
}
