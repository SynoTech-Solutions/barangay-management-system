<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Enums\BusinessStatus;
use App\Enums\DocumentKind;
use App\Enums\ImportProblem;
use App\Enums\LookupType;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\TableWriter;

final class BusinessesImporter extends BaseImporter
{
    public const string TABLE = 'businesses';

    public const int NAME_MAX = 150;

    public function entity(): BundleEntity
    {
        return BundleEntity::Businesses;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        $ids = TableWriter::reserveIds(self::TABLE, count($records));
        $rows = [];

        foreach ($records as $index => $record) {
            if ($this->alreadyImported($context, $record)) {
                $this->observe($context, $record, 'source_number', DocumentKind::Business, null);

                continue;
            }

            $ownerId = null;
            if ($record->ref('owner_resident_key') !== null) {
                $ownerId = $this->required($context, $record, 'owner_resident_key', BundleEntity::Residents);
                if ($ownerId === null) {
                    continue;
                }
            }

            $ownerName = $record->text('owner_name');
            if ($ownerId === null && $ownerName === null) {
                $context->failed($record->key(), ImportProblem::MissingName);

                continue;
            }

            $id = $ids[$index];
            $registeredAt = $context->utcOrNow($record->raw('registered_at'));
            [$number, $sourceNumber] = $this->number($context, $record, 'source_number', self::TABLE, 'business_number', DocumentKind::Business, $context->year($record->raw('registered_at')));

            $rows[] = [
                'id' => $id,
                'barangay_id' => $context->barangayId(),
                'business_number' => $number,
                'source_number' => $sourceNumber,
                'import_note' => $this->note($record),
                'name' => $this->uniqueName($context, $record, self::TABLE, 'name', $record->requiredText('name'), self::NAME_MAX),
                'owner_resident_id' => $ownerId,
                'owner_name' => $ownerId === null ? $ownerName : null,
                'category_id' => $context->lookups->findOrCreate(LookupType::BusinessCategory, $record->requiredText('category')),
                'nature_id' => $context->lookups->findOrCreate(LookupType::BusinessNature, $record->requiredText('nature')),
                'ownership_type_id' => $context->lookups->findOrCreate(LookupType::BusinessOwnershipType, $record->requiredText('ownership_type')),
                'purok_id' => $this->optional($context, $record, 'purok_key', BundleEntity::Puroks),
                'street_address' => $record->text('street_address'),
                'plate_number' => $record->text('plate_number'),
                'operating_since' => $record->raw('operating_since'),
                'capitalization' => $record->has('capitalization') ? $record->money('capitalization') : null,
                'gross_receipts' => $record->has('gross_receipts') ? $record->money('gross_receipts') : null,
                'status' => (BusinessStatus::tryFrom((string) $record->text('status')) ?? BusinessStatus::Active)->value,
                'created_at' => $registeredAt,
                'updated_at' => $context->timestamp,
            ];
            $this->remember($context, $record, self::TABLE, $id);
        }

        TableWriter::insert(self::TABLE, $rows);
    }
}
