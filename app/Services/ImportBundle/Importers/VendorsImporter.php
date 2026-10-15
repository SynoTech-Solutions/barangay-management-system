<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Services\ImportBundle\ImportContext;

final class VendorsImporter extends BaseImporter
{
    public const string TABLE = 'vendors';

    public function entity(): BundleEntity
    {
        return BundleEntity::Vendors;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        foreach ($records as $record) {
            if ($this->alreadyImported($context, $record)) {
                continue;
            }

            $id = $this->findOrInsert($context, self::TABLE, 'name', $record->requiredText('name'), [
                'address' => $record->requiredText('address'),
                'contact_number' => $record->requiredText('contact_number'),
                'email' => $record->text('email'),
                'contact_person' => $record->text('contact_person'),
                'position' => $record->text('position'),
                'tin' => $record->text('tin'),
                'is_active' => $record->bool('is_active', true),
            ]);

            $this->remember($context, $record, self::TABLE, $id);
        }
    }
}
