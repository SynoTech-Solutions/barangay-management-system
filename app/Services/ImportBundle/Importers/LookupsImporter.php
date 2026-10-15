<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Enums\LookupType;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\LookupResolver;

final class LookupsImporter extends BaseImporter
{
    public function entity(): BundleEntity
    {
        return BundleEntity::Lookups;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        foreach ($records as $record) {
            if ($this->alreadyImported($context, $record)) {
                continue;
            }

            $id = $context->lookups->findOrCreate(
                LookupType::from($record->requiredText('type')),
                $record->requiredText('label'),
                $record->int('sort_order') ?? 0,
                $record->bool('is_active', true),
            );

            $this->remember($context, $record, LookupResolver::TABLE, $id);
        }
    }
}
