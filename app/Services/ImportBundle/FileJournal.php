<?php

namespace App\Services\ImportBundle;

use App\Support\StoragePaths;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

final class FileJournal implements ChunkAware
{
    /**
     * @var list<string>
     */
    private array $written = [];

    public function __construct(private readonly bool $dryRun) {}

    public function put(string $path, string $contents): void
    {
        if ($this->dryRun) {
            return;
        }

        $this->disk()->put($path, $contents);
        $this->written[] = $path;
    }

    public function disk(): Filesystem
    {
        return Storage::disk(StoragePaths::PRIVATE_DISK);
    }

    public function begin(): void
    {
        $this->written = [];
    }

    public function commit(): void
    {
        $this->written = [];
    }

    public function rollback(): void
    {
        foreach ($this->written as $path) {
            $this->disk()->delete($path);
        }
        $this->written = [];
    }
}
