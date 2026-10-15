<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\TableWriter;

final class SmsTemplatesImporter extends BaseImporter
{
    public const string TABLE = 'sms_templates';

    private const int TITLE_MAX = 50;

    public function entity(): BundleEntity
    {
        return BundleEntity::SmsTemplates;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        foreach ($records as $record) {
            if ($this->alreadyImported($context, $record)) {
                continue;
            }

            $id = TableWriter::reserveId(self::TABLE);
            TableWriter::insert(self::TABLE, [[
                'id' => $id,
                'barangay_id' => $context->barangayId(),
                'title' => $this->uniqueName($context, $record, self::TABLE, 'title', $record->requiredText('title'), self::TITLE_MAX),
                'body' => (string) $record->raw('body'),
                'created_at' => $context->timestamp,
                'updated_at' => $context->timestamp,
            ]]);

            $this->remember($context, $record, self::TABLE, $id);
        }
    }
}
