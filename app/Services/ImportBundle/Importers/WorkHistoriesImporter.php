<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Enums\LookupType;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\Record;

final class WorkHistoriesImporter extends ResidentDetailImporter
{
    public function entity(): BundleEntity
    {
        return BundleEntity::WorkHistories;
    }

    protected function table(): string
    {
        return 'resident_work_histories';
    }

    protected function attributes(ImportContext $context, Record $record): array
    {
        $current = $record->bool('is_current');

        return [
            'company_id' => $context->lookups->findOrCreate(LookupType::Company, $record->requiredText('company')),
            'position_id' => $context->lookups->findOrCreate(LookupType::Position, $record->requiredText('position')),
            'employment_status_id' => $context->lookups->findOrCreate(LookupType::EmploymentStatus, $record->requiredText('employment_status')),
            'started_on' => $record->raw('started_on'),
            'ended_on' => $current ? null : $record->raw('ended_on'),
            'is_current' => $current,
        ];
    }
}
