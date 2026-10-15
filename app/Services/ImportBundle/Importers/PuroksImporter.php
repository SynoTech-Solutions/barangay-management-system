<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Services\ImportBundle\ImportContext;

final class PuroksImporter extends BaseImporter
{
    public const string TABLE = 'puroks';

    public function entity(): BundleEntity
    {
        return BundleEntity::Puroks;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        foreach ($records as $record) {
            if ($this->alreadyImported($context, $record)) {
                continue;
            }

            $id = $this->findOrInsert($context, self::TABLE, 'name', $record->requiredText('name'), [
                'is_active' => $record->bool('is_active', true),
            ]);

            $this->remember($context, $record, self::TABLE, $id);
        }
    }
}
