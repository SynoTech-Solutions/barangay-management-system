<?php

namespace App\Services\Sms;

use App\Domain\Residents\PhilippineMobileNumber;
use App\Models\Resident;
use App\Models\SmsGroup;
use App\Models\SmsGroupMember;
use Illuminate\Database\Eloquent\Builder;

final readonly class SmsRecipientResolver
{
    private const array RESIDENT_COLUMNS = ['residents.id', 'residents.mobile_number', 'residents.is_deceased', 'residents.deleted_at'];

    /**
     * @param  list<int>  $residentIds
     */
    public function fromResidentIds(array $residentIds): RecipientSet
    {
        $set = new RecipientSet;
        if ($residentIds === []) {
            return $set;
        }

        $residents = Resident::query()->withTrashed()->whereKey($residentIds)->orderBy('id')->get(self::RESIDENT_COLUMNS)->keyBy('id');

        foreach (array_values(array_unique($residentIds)) as $id) {
            $resident = $residents->get($id);
            $resident instanceof Resident ? $this->addResident($set, $resident) : $set->skip();
        }

        return $set;
    }

    /**
     * @param  Builder<Resident>  $residents
     */
    public function fromResidentQuery(Builder $residents): RecipientSet
    {
        $set = new RecipientSet;

        foreach ($residents->reorder()->orderBy('residents.id')->select(self::RESIDENT_COLUMNS)->cursor() as $resident) {
            $this->addResident($set, $resident);
        }

        return $set;
    }

    /**
     * @param  list<string>  $numbers
     */
    public function fromNumbers(array $numbers): RecipientSet
    {
        $set = new RecipientSet;

        foreach ($numbers as $number) {
            $e164 = PhilippineMobileNumber::toE164($number);
            $e164 === null ? $set->skip() : $set->add(new SmsRecipient($e164));
        }

        return $set;
    }

    public function fromGroup(SmsGroup $group): RecipientSet
    {
        $set = new RecipientSet;

        $members = SmsGroupMember::query()
            ->where('sms_group_id', $group->id)
            ->with(['resident' => fn ($query) => $query->select(self::RESIDENT_COLUMNS)])
            ->orderBy('id')
            ->get();

        foreach ($members as $member) {
            if ($member->resident_id === null) {
                $e164 = PhilippineMobileNumber::toE164($member->mobile_number);
                $e164 === null ? $set->skip() : $set->add(new SmsRecipient($e164));

                continue;
            }

            $member->resident === null ? $set->skip() : $this->addResident($set, $member->resident);
        }

        return $set;
    }

    public static function reachable(Resident $resident): ?string
    {
        if ($resident->deleted_at !== null || $resident->is_deceased) {
            return null;
        }

        return PhilippineMobileNumber::toE164($resident->mobile_number);
    }

    private function addResident(RecipientSet $set, Resident $resident): void
    {
        $number = self::reachable($resident);
        $number === null ? $set->skip() : $set->add(new SmsRecipient($number, $resident->id));
    }
}
