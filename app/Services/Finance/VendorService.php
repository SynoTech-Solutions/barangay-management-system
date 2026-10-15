<?php

namespace App\Services\Finance;

use App\Enums\AuditAction;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Audit\AuditSnapshot;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;

final readonly class VendorService
{
    private const array AUDITED_ATTRIBUTES = [...Vendor::DETAIL_ATTRIBUTES, 'is_active'];

    public function __construct(private AuditTrail $audit) {}

    /**
     * @param  array{name: string, address: string, contact_number: string, email: string|null, contact_person: string|null, position: string|null, tin: string|null}  $details
     */
    public function save(?Vendor $vendor, array $details, User $actor): Vendor
    {
        return DB::transaction(function () use ($vendor, $details, $actor): Vendor {
            $vendor ??= new Vendor;
            $before = $vendor->exists ? AuditSnapshot::of($vendor, self::AUDITED_ATTRIBUTES) : null;
            $vendor->fill($details);
            $vendor->updated_by = $actor->id;
            $vendor->save();

            $this->audit->record(AuditAction::VendorSaved, $vendor, $before, AuditSnapshot::of($vendor, self::AUDITED_ATTRIBUTES), $vendor->barangay_id, $actor->id);

            return $vendor;
        });
    }

    public function setActive(Vendor $vendor, bool $active, User $actor): void
    {
        DB::transaction(function () use ($vendor, $active, $actor): void {
            $vendor->is_active = $active;
            $vendor->updated_by = $actor->id;
            $vendor->save();

            $this->audit->record($active ? AuditAction::Reactivated : AuditAction::Deactivated, $vendor, ['is_active' => ! $active], ['is_active' => $active], $vendor->barangay_id, $actor->id);
        });
    }
}
