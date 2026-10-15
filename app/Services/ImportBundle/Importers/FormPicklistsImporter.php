<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\TableWriter;
use Illuminate\Support\Facades\DB;

final class FormPicklistsImporter extends BaseImporter
{
    public const string TABLE = 'form_picklists';

    public const string ITEM_TABLE = 'form_picklist_items';

    public function entity(): BundleEntity
    {
        return BundleEntity::FormPicklists;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        foreach ($records as $record) {
            if ($this->alreadyImported($context, $record)) {
                continue;
            }

            $picklistId = $this->findOrInsert($context, self::TABLE, 'name', $record->requiredText('name'), [
                'is_active' => $record->bool('is_active', true),
            ]);

            $existing = array_flip(array_map('mb_strtolower', DB::table(self::ITEM_TABLE)->where('form_picklist_id', $picklistId)->pluck('label')->all()));
            $rows = [];
            foreach ($record->strings('items') as $label) {
                if (isset($existing[mb_strtolower($label)])) {
                    continue;
                }

                $existing[mb_strtolower($label)] = true;
                $rows[] = [
                    'barangay_id' => $context->barangayId(),
                    'form_picklist_id' => $picklistId,
                    'label' => $label,
                    'is_active' => true,
                    'created_at' => $context->timestamp,
                    'updated_at' => $context->timestamp,
                ];
            }
            TableWriter::insert(self::ITEM_TABLE, $rows);

            $this->remember($context, $record, self::TABLE, $picklistId);
        }
    }
}
