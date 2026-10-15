<?php

namespace App\Queries;

use App\Domain\Residents\ResidentAge;
use App\Enums\ResidentFlag;
use App\Enums\ResidentSort;
use App\Models\Resident;
use App\Queries\Filters\ResidentListFilters;
use App\Support\Config\BmsSettings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class ResidentListQuery
{
    public function __construct(private BmsSettings $settings) {}

    /**
     * @return LengthAwarePaginator<int, Resident>
     */
    public function paginate(ResidentListFilters $filters, ?int $perPage): LengthAwarePaginator
    {
        return $this->builder($filters)
            ->with('purok:id,name')
            ->paginate($this->settings->pagination->clamp($perPage))
            ->withQueryString();
    }

    /**
     * @return Builder<Resident>
     */
    public function builder(ResidentListFilters $filters): Builder
    {
        $query = Resident::query()
            ->when($filters->removed, fn (Builder $q) => $q->onlyTrashed())
            ->matchingName($filters->name)
            ->when($filters->purokId !== null, fn (Builder $q) => $q->where('purok_id', $filters->purokId))
            ->when($filters->gender !== null, fn (Builder $q) => $q->where('gender', $filters->gender))
            ->when($filters->civilStatus !== null, fn (Builder $q) => $q->where('civil_status', $filters->civilStatus))
            ->when($filters->registeredVoter !== null, fn (Builder $q) => $q->where('is_registered_voter', $filters->registeredVoter))
            ->when($filters->sectorId !== null, fn (Builder $q) => $q->whereHas('sectors', fn (Builder $sector) => $sector->whereKey($filters->sectorId)))
            ->when(! $filters->includeIncomplete && ! $filters->has(ResidentFlag::Incomplete), fn (Builder $q) => $q->where('is_incomplete', false));

        $query
            ->when($filters->ageFrom !== null, fn (Builder $q) => $q->where('birthdate', '<=', $filters->today->subYears((int) $filters->ageFrom)->toDateString()))
            ->when($filters->ageTo !== null, fn (Builder $q) => $q->where('birthdate', '>', $filters->today->subYears((int) $filters->ageTo + 1)->toDateString()))
            ->when($filters->attribute !== null && $filters->attributeValues !== [], fn (Builder $q) => $q->whereIn((string) $filters->attribute, $filters->attributeValues));

        foreach ($filters->flags as $flag) {
            $this->applyFlag($query, $flag, $filters);
        }

        return $this->applySort($query, $filters);
    }

    /**
     * @param  Builder<Resident>  $query
     */
    private function applyFlag(Builder $query, ResidentFlag $flag, ResidentListFilters $filters): void
    {
        match ($flag) {
            ResidentFlag::Senior => $query->where('birthdate', '<=', $filters->today->subYears(ResidentAge::SENIOR_CITIZEN_AGE)->toDateString()),
            ResidentFlag::SeniorMember => $query->where('is_senior_citizen_member', true),
            ResidentFlag::Pwd => $query->where('is_pwd', true),
            ResidentFlag::Indigent => $query->where('is_indigent', true),
            ResidentFlag::SingleParent => $query->where('is_single_parent', true),
            ResidentFlag::LivingWithParents => $query->where('is_living_with_parents', true),
            ResidentFlag::PermanentResident => $query->where('is_permanent_resident', true),
            ResidentFlag::Deceased => $query->where('is_deceased', true),
            ResidentFlag::Living => $query->where('is_deceased', false),
            ResidentFlag::Incomplete => $query->where('is_incomplete', true),
        };
    }

    /**
     * @param  Builder<Resident>  $query
     * @return Builder<Resident>
     */
    private function applySort(Builder $query, ResidentListFilters $filters): Builder
    {
        $direction = $filters->direction->value;

        match ($filters->sort) {
            ResidentSort::Name => $query->orderBy('last_name', $direction)->orderBy('first_name', $direction)->orderBy('middle_name', $direction),
            ResidentSort::Number => $query->orderBy('resident_number', $direction),
            ResidentSort::Birthdate => $query->orderByRaw("birthdate {$direction} nulls last"),
            ResidentSort::Registered => $query->orderBy('created_at', $direction),
        };

        return $query->orderBy('id', $direction);
    }
}
