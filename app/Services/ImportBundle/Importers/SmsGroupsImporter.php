<?php

namespace App\Services\ImportBundle\Importers;

use App\Domain\Residents\PhilippineMobileNumber;
use App\Enums\BundleEntity;
use App\Enums\ImportProblem;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\TableWriter;
use Illuminate\Support\Facades\DB;

final class SmsGroupsImporter extends BaseImporter
{
    public const string TABLE = 'sms_groups';

    public const string MEMBER_TABLE = 'sms_group_members';

    public function entity(): BundleEntity
    {
        return BundleEntity::SmsGroups;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        foreach ($records as $record) {
            if ($this->alreadyImported($context, $record)) {
                continue;
            }

            $groupId = $this->findOrInsert($context, self::TABLE, 'name', $record->requiredText('name'), [
                'is_active' => $record->bool('is_active', true),
            ]);

            $members = DB::table(self::MEMBER_TABLE)->where('sms_group_id', $groupId)->get(['resident_id', 'mobile_number']);
            $seen = [];
            foreach ($members as $member) {
                $seen[$member->resident_id === null ? 'm:'.$member->mobile_number : 'r:'.$member->resident_id] = true;
            }

            $rows = [];
            foreach ($record->records('members') as $member) {
                $residentId = $this->optional($context, $member, 'resident_key', BundleEntity::Residents, $record->key());
                $mobile = $residentId === null ? PhilippineMobileNumber::toE164($member->raw('mobile_number')) : null;
                if ($residentId === null && $mobile === null) {
                    continue;
                }

                $slot = $residentId === null ? 'm:'.$mobile : 'r:'.$residentId;
                if (isset($seen[$slot])) {
                    $context->warn($record->key(), ImportProblem::DuplicateMember);

                    continue;
                }

                $seen[$slot] = true;
                $rows[] = [
                    'barangay_id' => $context->barangayId(),
                    'sms_group_id' => $groupId,
                    'resident_id' => $residentId,
                    'mobile_number' => $mobile,
                    'label' => $member->text('label'),
                    'created_at' => $context->timestamp,
                    'updated_at' => $context->timestamp,
                ];
            }
            TableWriter::insert(self::MEMBER_TABLE, $rows);

            $this->remember($context, $record, self::TABLE, $groupId);
        }
    }
}
