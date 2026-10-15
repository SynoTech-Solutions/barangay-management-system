<?php

namespace App\Services\Finance;

use App\Enums\AuditAction;
use App\Models\CollectionType;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;

final readonly class CollectionTypeService
{
    public function __construct(private AuditTrail $audit) {}

    public function save(?CollectionType $type, string $name, User $actor): CollectionType
    {
        return DB::transaction(function () use ($type, $name, $actor): CollectionType {
            $type ??= new CollectionType;
            $before = $type->exists ? ['name' => $type->name] : null;
            $type->name = $name;
            $type->updated_by = $actor->id;
            $type->save();

            $this->audit->record(AuditAction::CollectionTypeSaved, $type, $before, ['name' => $name], $type->barangay_id, $actor->id);

            return $type;
        });
    }

    public function setActive(CollectionType $type, bool $active, User $actor): void
    {
        DB::transaction(function () use ($type, $active, $actor): void {
            $type->is_active = $active;
            $type->updated_by = $actor->id;
            $type->save();

            $this->audit->record($active ? AuditAction::Reactivated : AuditAction::Deactivated, $type, ['is_active' => ! $active], ['is_active' => $active], $type->barangay_id, $actor->id);
        });
    }
}
