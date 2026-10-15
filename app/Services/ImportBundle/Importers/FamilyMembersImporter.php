<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Enums\LookupType;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\Record;

final class FamilyMembersImporter extends ResidentDetailImporter
{
    public function entity(): BundleEntity
    {
        return BundleEntity::FamilyMembers;
    }

    protected function table(): string
    {
        return 'family_members';
    }

    protected function attributes(ImportContext $context, Record $record): array
    {
        return [
            'relationship_id' => $context->lookups->findOrCreate(LookupType::Relationship, $record->requiredText('relationship')),
            'full_name' => $record->requiredText('full_name'),
            'birthdate' => $record->raw('birthdate'),
            'birth_year' => $record->int('birth_year'),
            'related_resident_id' => $this->optional($context, $record, 'related_resident_key', BundleEntity::Residents),
        ];
    }
}
