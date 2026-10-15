<?php

namespace App\Services\ImportBundle;

interface ChunkAware
{
    public function begin(): void;

    public function commit(): void;

    public function rollback(): void;
}
