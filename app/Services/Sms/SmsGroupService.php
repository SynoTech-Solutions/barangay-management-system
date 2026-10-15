<?php

namespace App\Services\Sms;

use App\Domain\Residents\NameNormalizer;
use App\Domain\Residents\PhilippineMobileNumber;
use App\Domain\Sms\MaskedNumber;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Exceptions\BmsException;
use App\Models\SmsGroup;
use App\Models\SmsGroupMember;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class SmsGroupService
{
    public function __construct(private AuditTrail $audit) {}

    public function create(string $name, User $actor): SmsGroup
    {
        $name = NameNormalizer::normalize($name) ?? '';
        $this->ensureNameAvailable($name, null);

        return $this->guardName($name, fn (): SmsGroup => DB::transaction(function () use ($name, $actor): SmsGroup {
            $group = new SmsGroup(['name' => $name]);
            $group->created_by = $actor->id;
            $group->updated_by = $actor->id;
            $group->save();

            $this->audit->record(AuditAction::SmsGroupCreated, $group, after: ['name' => $name], barangayId: $group->barangay_id, actorId: $actor->id);

            return $group;
        }));
    }

    public function rename(SmsGroup $group, string $name, User $actor): SmsGroup
    {
        $name = NameNormalizer::normalize($name) ?? '';
        $this->ensureNameAvailable($name, $group->id);

        return $this->guardName($name, fn (): SmsGroup => DB::transaction(function () use ($group, $name, $actor): SmsGroup {
            $before = ['name' => $group->name];
            $group->name = $name;
            $group->updated_by = $actor->id;
            $group->save();

            $this->audit->record(AuditAction::SmsGroupRenamed, $group, $before, ['name' => $name], $group->barangay_id, $actor->id);

            return $group;
        }));
    }

    public function setActive(SmsGroup $group, bool $active, User $actor): void
    {
        DB::transaction(function () use ($group, $active, $actor): void {
            $group->is_active = $active;
            $group->updated_by = $actor->id;
            $group->save();

            $this->audit->record(
                $active ? AuditAction::SmsGroupReactivated : AuditAction::SmsGroupDeactivated,
                $group,
                barangayId: $group->barangay_id,
                actorId: $actor->id,
            );
        });
    }

    /**
     * @return array{added: int, already: int, skipped: int}
     */
    public function addResidents(SmsGroup $group, RecipientSet $residents, User $actor): array
    {
        $this->ensureActive($group);

        return DB::transaction(function () use ($group, $residents, $actor): array {
            $residentIds = array_values(array_filter(array_map(fn (SmsRecipient $recipient): ?int => $recipient->residentId, $residents->all())));
            $existing = SmsGroupMember::query()
                ->where('sms_group_id', $group->id)
                ->whereIn('resident_id', $residentIds)
                ->pluck('resident_id')
                ->all();

            $added = 0;
            foreach (array_diff($residentIds, $existing) as $residentId) {
                $member = new SmsGroupMember(['sms_group_id' => $group->id, 'resident_id' => $residentId]);
                $member->added_by = $actor->id;
                $member->save();
                $added++;
            }

            $result = ['added' => $added, 'already' => count($existing), 'skipped' => $residents->skipped() + $residents->duplicates()];

            $this->audit->record(AuditAction::SmsGroupMembersAdded, $group, after: $result, barangayId: $group->barangay_id, actorId: $actor->id);

            return $result;
        });
    }

    public function addNumber(SmsGroup $group, string $number, ?string $label, User $actor): SmsGroupMember
    {
        $this->ensureActive($group);
        $e164 = (string) PhilippineMobileNumber::toE164($number);

        try {
            return DB::transaction(function () use ($group, $e164, $label, $actor): SmsGroupMember {
                $member = new SmsGroupMember(['sms_group_id' => $group->id, 'mobile_number' => $e164, 'label' => NameNormalizer::normalize($label)]);
                $member->added_by = $actor->id;
                $member->save();

                $this->audit->record(AuditAction::SmsGroupMembersAdded, $group, after: [
                    'added' => 1,
                    'recipient' => MaskedNumber::of($e164),
                ], barangayId: $group->barangay_id, actorId: $actor->id);

                return $member;
            });
        } catch (UniqueConstraintViolationException) {
            throw BmsException::of(ErrorCode::SmsGroupMemberTaken);
        }
    }

    public function removeMember(SmsGroup $group, SmsGroupMember $member, User $actor): void
    {
        DB::transaction(function () use ($group, $member, $actor): void {
            $before = $member->resident_id !== null
                ? ['resident_id' => $member->resident_id]
                : ['recipient' => MaskedNumber::of($member->mobile_number)];
            $member->delete();

            $this->audit->record(AuditAction::SmsGroupMemberRemoved, $group, $before, null, $group->barangay_id, $actor->id);
        });
    }

    public function ensureActive(SmsGroup $group): void
    {
        if (! $group->is_active) {
            throw BmsException::of(ErrorCode::SmsGroupInactive, ['name' => $group->name]);
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $write
     * @return T
     */
    private function guardName(string $name, callable $write): mixed
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            throw BmsException::of(ErrorCode::SmsGroupNameTaken, ['name' => $name]);
        }
    }

    private function ensureNameAvailable(string $name, ?int $exceptId): void
    {
        $taken = SmsGroup::query()
            ->whereRaw('lower(name) = lower(?)', [$name])
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($taken) {
            throw BmsException::of(ErrorCode::SmsGroupNameTaken, ['name' => $name]);
        }
    }
}
