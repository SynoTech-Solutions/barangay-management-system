<?php

namespace App\Services\ImportBundle;

use App\Enums\BundleEntity;

interface EntityImporter
{
    public function entity(): BundleEntity;

    public function chunkSize(): int;

    /**
     * @param  list<Record>  $records
     */
    public function importChunk(array $records, ImportContext $context): void;

    public function finish(ImportContext $context): void;
}
