<?php

namespace App\Services\Businesses;

use App\Domain\Audit\AuditRedactor;
use App\Enums\AuditAction;
use App\Enums\BusinessStatus;
use App\Enums\DocumentKind;
use App\Models\Business;
use App\Models\User;
use App\Services\Audit\AuditSnapshot;
use App\Services\Audit\AuditTrail;
use App\Services\Numbering\DocumentNumberAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class BusinessRegistry
{
    private const array AUDITED_ATTRIBUTES = ['business_number', 'status', ...Business::DETAIL_ATTRIBUTES];

    public function __construct(
        private AuditTrail $audit,
        private DocumentNumberAllocator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public function register(array $details, User $actor): Business
    {
        return DB::transaction(function () use ($details, $actor): Business {
            $business = new Business;
            $business->fill($details);
            $business->business_number = $this->numbers->next(DocumentKind::Business, CarbonImmutable::now()->year);
            $business->status = BusinessStatus::Active;
            $business->registered_by = $actor->id;
            $business->updated_by = $actor->id;
            $business->save();

            $this->audit->record(AuditAction::BusinessRegistered, $business, after: $this->snapshot($business), barangayId: $business->barangay_id, actorId: $actor->id);

            return $business;
        });
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function update(Business $business, array $details, User $actor): Business
    {
        return DB::transaction(function () use ($business, $details, $actor): Business {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            $before = $this->snapshot($business);
            $business->fill($details);
            $business->updated_by = $actor->id;
            $business->save();

            [$changedBefore, $changedAfter] = AuditRedactor::changedOnly($before, $this->snapshot($business));
            if ($changedBefore !== [] || $changedAfter !== []) {
                $this->audit->record(AuditAction::BusinessUpdated, $business, $changedBefore, $changedAfter, $business->barangay_id, $actor->id);
            }

            return $business;
        });
    }

    public function changeStatus(Business $business, BusinessStatus $status, User $actor): Business
    {
        return DB::transaction(function () use ($business, $status, $actor): Business {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);

            if ($business->status === $status) {
                return $business;
            }

            $before = ['status' => $business->status->value];
            $business->status = $status;
            $business->updated_by = $actor->id;
            $business->save();

            $this->audit->record(AuditAction::BusinessStatusChanged, $business, $before, ['status' => $status->value], $business->barangay_id, $actor->id);

            return $business;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Business $business): array
    {
        return AuditSnapshot::of($business, self::AUDITED_ATTRIBUTES);
    }
}
