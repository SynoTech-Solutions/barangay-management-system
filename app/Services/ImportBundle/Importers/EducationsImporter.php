<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BundleEntity;
use App\Enums\LookupType;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\Record;

final class EducationsImporter extends ResidentDetailImporter
{
    public function entity(): BundleEntity
    {
        return BundleEntity::Educations;
    }

    protected function table(): string
    {
        return 'resident_educations';
    }

    protected function attributes(ImportContext $context, Record $record): array
    {
        return [
            'school_id' => $context->lookups->findOrCreate(LookupType::School, $record->requiredText('school')),
            'course_id' => $context->lookups->findOrCreate(LookupType::Course, $record->requiredText('course')),
            'started_on' => $record->raw('started_on'),
            'ended_on' => $record->raw('ended_on'),
        ];
    }
}
