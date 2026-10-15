<?php

namespace Tests\Support;

use App\Domain\ImportBundle\BundleFormat;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class BundleWriter
{
    /**
     * @var list<string>
     */
    private static array $written = [];

    /**
     * @var array<string, list<array<string, mixed>>>
     */
    private array $entities = [];

    /**
     * @var array<string, string>
     */
    private array $rawEntities = [];

    /**
     * @var array<string, string>
     */
    private array $files = [];

    /**
     * @var array<string, mixed>
     */
    private array $manifest = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    public function with(string $entity, array $records): self
    {
        $this->entities[$entity] = $records;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function add(string $entity, array $record): self
    {
        $this->entities[$entity][] = $record;

        return $this;
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     */
    public function change(string $entity, string $key, callable $change): self
    {
        foreach ($this->entities[$entity] ?? [] as $index => $record) {
            if (($record['source_key'] ?? null) === $key) {
                $this->entities[$entity][$index] = $change($record);
            }
        }

        return $this;
    }

    public function without(string $entity): self
    {
        unset($this->entities[$entity], $this->rawEntities[$entity]);

        return $this;
    }

    public function raw(string $entity, string $contents): self
    {
        $this->rawEntities[$entity] = $contents;

        return $this;
    }

    public function file(string $relative, string $bytes): self
    {
        $this->files[$relative] = $bytes;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function manifest(array $values): self
    {
        $this->manifest = array_replace_recursive($this->manifest, $values);

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function records(string $entity): array
    {
        return $this->entities[$entity] ?? [];
    }

    public function write(): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bms-bundle-'.Str::lower(Str::random(12));
        File::ensureDirectoryExists($directory.DIRECTORY_SEPARATOR.BundleFormat::FILES_DIRECTORY);
        self::$written[] = $directory;

        $entities = [];
        foreach ($this->entities as $entity => $records) {
            $lines = array_map(fn (array $record): string => json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $records);
            File::put($directory.DIRECTORY_SEPARATOR.$entity.'.jsonl', $lines === [] ? '' : implode("\n", $lines)."\n");
            $entities[$entity] = ['file' => $entity.'.jsonl', 'count' => count($records)];
        }

        foreach ($this->rawEntities as $entity => $contents) {
            File::put($directory.DIRECTORY_SEPARATOR.$entity.'.jsonl', $contents);
            $entities[$entity] = ['file' => $entity.'.jsonl', 'count' => count(array_filter(explode("\n", $contents), fn (string $line): bool => trim($line) !== ''))];
        }

        foreach ($this->files as $relative => $bytes) {
            $path = $directory.DIRECTORY_SEPARATOR.BundleFormat::FILES_DIRECTORY.DIRECTORY_SEPARATOR.$relative;
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $bytes);
        }

        $manifest = array_replace_recursive([
            'format' => BundleFormat::NAME,
            'version' => BundleFormat::VERSION,
            'generated_at' => '2026-10-01T08:00:00+08:00',
            'generator' => ['name' => 'test-writer', 'version' => '0.0.1'],
            'entities' => $entities,
        ], $this->manifest);
        File::put($directory.DIRECTORY_SEPARATOR.BundleFormat::MANIFEST_FILE, json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $directory;
    }

    public static function cleanup(): void
    {
        foreach (self::$written as $directory) {
            File::deleteDirectory($directory);
        }
        self::$written = [];
    }

    public static function png(): string
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public static function jpeg(): string
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagejpeg($image);

        return (string) ob_get_clean();
    }
}
