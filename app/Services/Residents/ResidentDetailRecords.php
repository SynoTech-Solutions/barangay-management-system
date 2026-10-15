<?php

namespace App\Services\Residents;

use App\Domain\Audit\AuditRedactor;
use App\Enums\AuditAction;
use App\Models\Resident;
use App\Models\User;
use App\Services\Audit\AuditSnapshot;
use App\Services\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final readonly class ResidentDetailRecords
{
    public function __construct(private AuditTrail $audit) {}

    /**
     * @template TRecord of Model
     *
     * @param  TRecord  $record
     * @param  array<string, mixed>  $attributes
     * @return TRecord
     */
    public function create(Resident $resident, Model $record, array $attributes, User $actor): Model
    {
        return DB::transaction(function () use ($resident, $record, $attributes, $actor): Model {
            $record->fill($attributes);
            $record->setAttribute('resident_id', $resident->id);
            $record->setAttribute('barangay_id', $resident->barangay_id);
            $record->setAttribute('created_by', $actor->id);
            $record->setAttribute('updated_by', $actor->id);
            $record->save();

            $this->audit->record(AuditAction::Created, $record, after: $this->snapshot($record), barangayId: $resident->barangay_id, actorId: $actor->id);

            return $record;
        });
    }

    /**
     * @template TRecord of Model
     *
     * @param  TRecord  $record
     * @param  array<string, mixed>  $attributes
     * @return TRecord
     */
    public function update(Model $record, array $attributes, User $actor): Model
    {
        return DB::transaction(function () use ($record, $attributes, $actor): Model {
            $before = $this->snapshot($record);
            $record->fill($attributes);
            $record->setAttribute('updated_by', $actor->id);
            $record->save();

            [$changedBefore, $changedAfter] = AuditRedactor::changedOnly($before, $this->snapshot($record));

            if ($changedBefore !== [] || $changedAfter !== []) {
                $this->audit->record(AuditAction::Updated, $record, $changedBefore, $changedAfter, $this->barangayId($record), $actor->id);
            }

            return $record;
        });
    }

    public function remove(Model $record, User $actor): void
    {
        DB::transaction(function () use ($record, $actor): void {
            $before = $this->snapshot($record);
            $record->setAttribute('deleted_by', $actor->id);
            $record->save();
            $record->delete();

            $this->audit->record(AuditAction::Deleted, $record, before: $before, barangayId: $this->barangayId($record), actorId: $actor->id);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Model $record): array
    {
        return AuditSnapshot::of($record, array_values($record->getFillable()));
    }

    private function barangayId(Model $record): int
    {
        return (int) $record->getAttribute('barangay_id');
    }
}
