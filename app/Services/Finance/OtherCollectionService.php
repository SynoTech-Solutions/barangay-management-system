<?php

namespace App\Services\Finance;

use App\Domain\Audit\AuditRedactor;
use App\Enums\AuditAction;
use App\Enums\DocumentKind;
use App\Enums\ErrorCode;
use App\Exceptions\BmsException;
use App\Models\OtherCollection;
use App\Models\Resident;
use App\Models\User;
use App\Services\Audit\AuditSnapshot;
use App\Services\Audit\AuditTrail;
use App\Services\Numbering\DocumentNumberAllocator;
use App\Services\Receipts\OfficialReceiptRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class OtherCollectionService
{
    private const array AUDITED_ATTRIBUTES = ['collection_number', ...OtherCollection::DETAIL_ATTRIBUTES];

    public function __construct(
        private AuditTrail $audit,
        private DocumentNumberAllocator $numbers,
        private OfficialReceiptRegistry $receipts,
    ) {}

    /**
     * @param  array{collection_type_id: int, resident_id: int|null, payer_name: string|null, or_number: string, amount: string, paid_on: string, remarks: string|null}  $details
     */
    public function record(array $details, User $actor): OtherCollection
    {
        return DB::transaction(function () use ($details, $actor): OtherCollection {
            $collection = new OtherCollection;
            $this->fill($collection, $details);
            $collection->collection_number = $this->numbers->next(DocumentKind::OtherCollection, CarbonImmutable::parse($details['paid_on'])->year);
            $collection->recorded_by = $actor->id;
            $collection->recorded_at = CarbonImmutable::now();
            $collection->save();
            $this->receipts->sync($collection);

            $this->audit->record(AuditAction::CollectionRecorded, $collection, null, AuditSnapshot::of($collection, self::AUDITED_ATTRIBUTES), $collection->barangay_id, $actor->id);

            return $collection;
        });
    }

    /**
     * @param  array{collection_type_id: int, resident_id: int|null, payer_name: string|null, or_number: string, amount: string, paid_on: string, remarks: string|null}  $details
     */
    public function update(OtherCollection $collection, array $details, User $actor): OtherCollection
    {
        return DB::transaction(function () use ($collection, $details, $actor): OtherCollection {
            $locked = $this->lockActive($collection);
            $before = AuditSnapshot::of($locked, self::AUDITED_ATTRIBUTES);
            $this->fill($locked, $details);
            $locked->updated_by = $actor->id;
            $locked->save();
            $this->receipts->sync($locked);

            [$changedBefore, $changedAfter] = AuditRedactor::changedOnly($before, AuditSnapshot::of($locked, self::AUDITED_ATTRIBUTES));
            $this->audit->record(AuditAction::CollectionUpdated, $locked, $changedBefore, $changedAfter, $locked->barangay_id, $actor->id);

            return $locked;
        });
    }

    public function cancel(OtherCollection $collection, string $reason, User $actor): OtherCollection
    {
        return DB::transaction(function () use ($collection, $reason, $actor): OtherCollection {
            $locked = $this->lockActive($collection);
            $locked->cancelled_by = $actor->id;
            $locked->cancelled_at = CarbonImmutable::now();
            $locked->cancel_reason = $reason;
            $locked->save();
            $this->receipts->void($locked);

            $this->audit->record(AuditAction::CollectionCancelled, $locked, null, ['collection_number' => $locked->collection_number, 'reason' => $reason], $locked->barangay_id, $actor->id);

            return $locked;
        });
    }

    /**
     * @param  array{collection_type_id: int, resident_id: int|null, payer_name: string|null, or_number: string, amount: string, paid_on: string, remarks: string|null}  $details
     */
    private function fill(OtherCollection $collection, array $details): void
    {
        $payer = $details['resident_id'] === null ? null : Resident::query()->findOrFail($details['resident_id']);

        $collection->fill([
            ...$details,
            'payer_name' => $payer === null ? (string) $details['payer_name'] : $payer->full_name,
        ]);
    }

    private function lockActive(OtherCollection $collection): OtherCollection
    {
        $locked = OtherCollection::query()->lockForUpdate()->findOrFail($collection->id);
        if ($locked->isCancelled()) {
            throw BmsException::of(ErrorCode::CollectionCancelled, ['number' => $locked->collection_number]);
        }

        return $locked;
    }
}
