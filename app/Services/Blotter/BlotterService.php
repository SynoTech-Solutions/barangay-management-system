<?php

namespace App\Services\Blotter;

use App\Domain\Audit\AuditRedactor;
use App\Enums\AuditAction;
use App\Enums\BlotterPartyRole;
use App\Enums\BlotterStatus;
use App\Enums\DocumentKind;
use App\Enums\ErrorCode;
use App\Exceptions\BmsException;
use App\Models\Blotter;
use App\Models\BlotterParty;
use App\Models\User;
use App\Services\Audit\AuditSnapshot;
use App\Services\Audit\AuditTrail;
use App\Services\Numbering\DocumentNumberAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class BlotterService
{
    private const array AUDITED_ATTRIBUTES = ['blotter_number', 'status', ...Blotter::DETAIL_ATTRIBUTES];

    public function __construct(
        private AuditTrail $audit,
        private DocumentNumberAllocator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $details
     * @param  list<array{role: BlotterPartyRole, resident_id: int|null, name: string|null, address: string|null}>  $parties
     */
    public function record(array $details, array $parties, User $actor): Blotter
    {
        return DB::transaction(function () use ($details, $parties, $actor): Blotter {
            $now = CarbonImmutable::now();

            $blotter = new Blotter;
            $blotter->fill($details);
            $blotter->blotter_number = $this->numbers->next(DocumentKind::Blotter, $now->year);
            $blotter->status = BlotterStatus::Open;
            $blotter->reported_at = $now;
            $blotter->recorded_by = $actor->id;
            $blotter->updated_by = $actor->id;
            $blotter->save();
            $this->replaceParties($blotter, $parties);

            $this->audit->record(AuditAction::BlotterRecorded, $blotter, after: $this->snapshot($blotter), barangayId: $blotter->barangay_id, actorId: $actor->id);

            return $blotter;
        });
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  list<array{role: BlotterPartyRole, resident_id: int|null, name: string|null, address: string|null}>  $parties
     */
    public function update(Blotter $blotter, array $details, array $parties, User $actor): Blotter
    {
        return DB::transaction(function () use ($blotter, $details, $parties, $actor): Blotter {
            $blotter = $this->lockOpen($blotter);
            $before = $this->snapshot($blotter);

            $blotter->fill($details);
            $blotter->updated_by = $actor->id;
            $blotter->save();
            $this->replaceParties($blotter, $parties);

            [$changedBefore, $changedAfter] = AuditRedactor::changedOnly($before, $this->snapshot($blotter));
            if ($changedBefore !== [] || $changedAfter !== []) {
                $this->audit->record(AuditAction::BlotterUpdated, $blotter, $changedBefore, $changedAfter, $blotter->barangay_id, $actor->id);
            }

            return $blotter;
        });
    }

    public function resolve(Blotter $blotter, ?string $notes, User $actor): Blotter
    {
        return DB::transaction(function () use ($blotter, $notes, $actor): Blotter {
            $blotter = $this->lockOpen($blotter);
            $blotter->status = BlotterStatus::Resolved;
            $blotter->resolved_at = CarbonImmutable::now();
            $blotter->resolved_by = $actor->id;
            $blotter->resolution_notes = $notes;
            $blotter->updated_by = $actor->id;
            $blotter->save();

            $this->audit->record(AuditAction::BlotterResolved, $blotter, ['status' => BlotterStatus::Open->value], ['status' => $blotter->status->value, 'resolution_notes' => $notes], $blotter->barangay_id, $actor->id);

            return $blotter;
        });
    }

    public function cancel(Blotter $blotter, string $reason, User $actor): Blotter
    {
        return DB::transaction(function () use ($blotter, $reason, $actor): Blotter {
            $blotter = $this->lockOpen($blotter);
            $blotter->status = BlotterStatus::Cancelled;
            $blotter->cancelled_at = CarbonImmutable::now();
            $blotter->cancelled_by = $actor->id;
            $blotter->cancel_reason = $reason;
            $blotter->updated_by = $actor->id;
            $blotter->save();

            $this->audit->record(AuditAction::BlotterCancelled, $blotter, ['status' => BlotterStatus::Open->value], ['status' => $blotter->status->value, 'cancel_reason' => $reason], $blotter->barangay_id, $actor->id);

            return $blotter;
        });
    }

    public function lockOpen(Blotter $blotter): Blotter
    {
        $locked = Blotter::query()->lockForUpdate()->findOrFail($blotter->id);

        if (! $locked->isOpen()) {
            throw BmsException::of(ErrorCode::BlotterNotOpen, ['number' => $locked->blotter_number]);
        }

        return $locked;
    }

    /**
     * @param  list<array{role: BlotterPartyRole, resident_id: int|null, name: string|null, address: string|null}>  $parties
     */
    private function replaceParties(Blotter $blotter, array $parties): void
    {
        BlotterParty::query()->where('blotter_id', $blotter->id)->delete();

        foreach ($parties as $position => $details) {
            $party = new BlotterParty([...$details, 'position' => $position]);
            $party->blotter_id = $blotter->id;
            $party->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Blotter $blotter): array
    {
        $parties = BlotterParty::query()->where('blotter_id', $blotter->id)->get(['role', 'resident_id']);
        $residentIds = fn (BlotterPartyRole $role): array => $parties
            ->filter(fn (BlotterParty $party): bool => $party->role === $role && $party->resident_id !== null)
            ->pluck('resident_id')
            ->sort()
            ->values()
            ->all();

        return [
            ...AuditSnapshot::of($blotter, self::AUDITED_ATTRIBUTES),
            'complainant_resident_ids' => $residentIds(BlotterPartyRole::Complainant),
            'respondent_resident_ids' => $residentIds(BlotterPartyRole::Respondent),
            'party_count' => $parties->count(),
        ];
    }
}
