<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Enums\ImportProblem;
use App\Services\ImportBundle\ImportContext;

final class ColorCategoriesImporter extends BaseImporter
{
    public const string TABLE = 'color_categories';

    public function entity(): BundleEntity
    {
        return BundleEntity::ColorCategories;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        foreach ($records as $record) {
            if ($this->alreadyImported($context, $record)) {
                continue;
            }

            if (! $context->colorTagsEnabled) {
                $context->skipped($record->key(), ImportProblem::ColorTagSkipped);

                continue;
            }

            $id = $this->findOrInsert($context, self::TABLE, 'name', $record->requiredText('name'), [
                'color_hex' => strtolower($record->requiredText('color_hex')),
                'is_active' => $record->bool('is_active', true),
            ]);

            $this->remember($context, $record, self::TABLE, $id);
        }
    }
}
