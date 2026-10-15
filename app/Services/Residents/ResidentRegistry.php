<?php

namespace App\Services\Residents;

use App\Domain\Audit\AuditRedactor;
use App\Domain\Residents\NameNormalizer;
use App\Enums\AuditAction;
use App\Enums\DocumentKind;
use App\Models\Resident;
use App\Models\User;
use App\Services\Audit\AuditSnapshot;
use App\Services\Audit\AuditTrail;
use App\Services\Numbering\DocumentNumberAllocator;
use Illuminate\Support\Facades\DB;

final readonly class ResidentRegistry
{
    private const array SYSTEM_ATTRIBUTES = ['resident_number', 'full_name', 'spouse_id', 'is_incomplete'];

    public function __construct(
        private AuditTrail $audit,
        private DocumentNumberAllocator $numbers,
        private SpouseLinker $spouses,
    ) {}

    /**
     * @param  array<string, mixed>  $profile
     * @param  list<int>|null  $sectorIds
     */
    public function register(array $profile, ?int $spouseId, ?array $sectorIds, User $actor): Resident
    {
        return DB::transaction(function () use ($profile, $spouseId, $sectorIds, $actor): Resident {
            $resident = $this->newResident($profile, false, $actor);

            if ($sectorIds !== null) {
                $this->syncSectors($resident, $sectorIds);
            }

            $this->spouses->link($resident, $spouseId, $actor);

            $this->audit->record(AuditAction::ResidentCreated, $resident, after: $this->snapshot($resident), barangayId: $resident->barangay_id, actorId: $actor->id);

            return $resident;
        });
    }

    /**
     * @param  array{last_name: string, first_name: string, middle_name: string|null, name_extension: string|null, birthdate: string|null}  $profile
     */
    public function quickAdd(array $profile, User $actor): Resident
    {
        return DB::transaction(function () use ($profile, $actor): Resident {
            $resident = $this->newResident($profile, true, $actor);

            $this->audit->record(AuditAction::ResidentCreated, $resident, after: $this->snapshot($resident), barangayId: $resident->barangay_id, actorId: $actor->id);

            return $resident;
        });
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  list<int>|null  $sectorIds
     */
    public function update(Resident $resident, array $profile, ?int $spouseId, ?array $sectorIds, User $actor): Resident
    {
        return DB::transaction(function () use ($resident, $profile, $spouseId, $sectorIds, $actor): Resident {
            $before = $this->snapshot($resident);

            $resident->fill($profile);
            $resident->full_name = NameNormalizer::fullName($resident->last_name, $resident->first_name, $resident->middle_name, $resident->name_extension);
            $resident->is_incomplete = false;
            $resident->updated_by = $actor->id;
            $resident->save();

            if ($sectorIds !== null) {
                $this->syncSectors($resident, $sectorIds);
            }

            $this->spouses->link($resident, $spouseId, $actor);

            [$changedBefore, $changedAfter] = AuditRedactor::changedOnly($before, $this->snapshot($resident));

            if ($changedAfter !== [] || $changedBefore !== []) {
                $this->audit->record(AuditAction::ResidentUpdated, $resident, $changedBefore, $changedAfter, $resident->barangay_id, $actor->id);
            }

            return $resident;
        });
    }

    public function delete(Resident $resident, User $actor): void
    {
        DB::transaction(function () use ($resident, $actor): void {
            $resident->deleted_by = $actor->id;
            $resident->save();
            $resident->delete();

            $this->audit->record(AuditAction::ResidentDeleted, $resident, barangayId: $resident->barangay_id, actorId: $actor->id);
        });
    }

    public function restore(Resident $resident, User $actor): void
    {
        DB::transaction(function () use ($resident, $actor): void {
            $resident->deleted_by = null;
            $resident->updated_by = $actor->id;
            $resident->restore();

            $this->audit->record(AuditAction::ResidentRestored, $resident, barangayId: $resident->barangay_id, actorId: $actor->id);
        });
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function newResident(array $profile, bool $incomplete, User $actor): Resident
    {
        $resident = new Resident;
        $resident->fill($profile);
        $resident->full_name = NameNormalizer::fullName($resident->last_name, $resident->first_name, $resident->middle_name, $resident->name_extension);
        $resident->resident_number = $this->numbers->next(DocumentKind::Resident, now()->year);
        $resident->is_incomplete = $incomplete;
        $resident->created_by = $actor->id;
        $resident->updated_by = $actor->id;
        $resident->save();

        return $resident;
    }

    /**
     * @param  list<int>  $sectorIds
     */
    private function syncSectors(Resident $resident, array $sectorIds): void
    {
        $resident->sectors()->sync(array_fill_keys(array_values(array_unique($sectorIds)), ['barangay_id' => $resident->barangay_id]));
        $resident->unsetRelation('sectors');
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Resident $resident): array
    {
        $sectorIds = $resident->sectors()->orderBy('lookup_values.id')->pluck('lookup_values.id')->map(fn ($id): int => (int) $id)->all();

        return [
            ...AuditSnapshot::of($resident, [...self::SYSTEM_ATTRIBUTES, ...Resident::PROFILE_ATTRIBUTES]),
            'sector_ids' => $sectorIds,
        ];
    }
}
