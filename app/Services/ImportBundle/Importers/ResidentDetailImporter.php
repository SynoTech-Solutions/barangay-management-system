<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\Record;
use App\Services\ImportBundle\TableWriter;

abstract class ResidentDetailImporter extends BaseImporter
{
    abstract protected function table(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function attributes(ImportContext $context, Record $record): array;

    public function importChunk(array $records, ImportContext $context): void
    {
        $ids = TableWriter::reserveIds($this->table(), count($records));
        $rows = [];

        foreach ($records as $index => $record) {
            if ($this->alreadyImported($context, $record)) {
                continue;
            }

            $residentId = $this->required($context, $record, 'resident_key', BundleEntity::Residents);
            if ($residentId === null) {
                continue;
            }

            $rows[] = [
                ...$this->attributes($context, $record),
                'id' => $ids[$index],
                'barangay_id' => $context->barangayId(),
                'resident_id' => $residentId,
                'created_at' => $context->timestamp,
                'updated_at' => $context->timestamp,
                'deleted_at' => $context->utc($record->raw('deleted_at')),
            ];
            $this->remember($context, $record, $this->table(), $ids[$index]);
        }

        TableWriter::insert($this->table(), $rows);
    }
}
