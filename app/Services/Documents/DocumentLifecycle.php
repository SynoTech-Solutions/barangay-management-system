<?php

namespace App\Services\Documents;

use App\Domain\Audit\AuditRedactor;
use App\Enums\DocumentEvent;
use App\Enums\ErrorCode;
use App\Enums\FeatureFlag;
use App\Enums\IssuedDocumentStatus;
use App\Exceptions\BmsException;
use App\Models\IssuedDocument;
use App\Models\User;
use App\Services\Audit\AuditSnapshot;
use App\Services\Audit\AuditTrail;
use App\Services\Features\FeatureFlags;
use App\Services\Receipts\OfficialReceiptRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class DocumentLifecycle
{
    private const string NUMBER_SEPARATOR = ', ';

    public function __construct(
        private AuditTrail $audit,
        private FeatureFlags $features,
        private OfficialReceiptRegistry $receipts,
    ) {}

    public function releaseStepEnabled(): bool
    {
        return $this->features->enabled(FeatureFlag::DocumentReleaseStep);
    }

    /**
     * @template TDocument of IssuedDocument
     *
     * @param  TDocument  $document
     * @param  list<string>  $audited
     * @param  array<string, mixed>  $extra
     * @return TDocument
     */
    public function issue(IssuedDocument $document, User $actor, CarbonImmutable $at, array $audited, array $extra = []): IssuedDocument
    {
        $document->status = IssuedDocumentStatus::Issued;
        $document->issued_by = $actor->id;
        $document->issued_at = $at;
        $document->updated_by = $actor->id;

        if (! $this->releaseStepEnabled()) {
            $document->status = IssuedDocumentStatus::Released;
            $document->released_at = $at;
            $document->released_by = $actor->id;
        }

        $document->save();
        $this->receipts->sync($document);

        $this->audit->record(
            $document->auditAction(DocumentEvent::Issued),
            $document,
            after: [...AuditSnapshot::of($document, ['status', ...$audited]), ...$extra],
            barangayId: $document->barangay_id,
            actorId: $actor->id,
        );

        return $document;
    }

    /**
     * @template TDocument of IssuedDocument
     *
     * @param  TDocument  $document
     * @param  list<string>  $audited
     * @param  callable(TDocument): mixed  $apply
     * @param  null|callable(TDocument): array<string, mixed>  $extra
     * @return TDocument
     */
    public function update(IssuedDocument $document, User $actor, array $audited, callable $apply, ?callable $extra = null): IssuedDocument
    {
        return DB::transaction(function () use ($document, $actor, $audited, $apply, $extra): IssuedDocument {
            /** @var TDocument $locked */
            $locked = $document::query()->lockForUpdate()->findOrFail($document->id);

            if (! $locked->isPending()) {
                throw BmsException::of(ErrorCode::DocumentNotEditable, ['number' => $locked->documentNumber()]);
            }

            $before = AuditSnapshot::of($locked, $audited);
            $apply($locked);
            $locked->updated_by = $actor->id;
            $locked->save();
            $this->receipts->sync($locked);

            [$changedBefore, $changedAfter] = AuditRedactor::changedOnly($before, AuditSnapshot::of($locked, $audited));
            $additional = $extra === null ? [] : $extra($locked);
            $changedAfter = [...$changedAfter, ...$additional];
            if ($changedBefore !== [] || $changedAfter !== []) {
                $this->audit->record($locked->auditAction(DocumentEvent::Updated), $locked, $changedBefore, $changedAfter, $locked->barangay_id, $actor->id);
            }

            return $locked;
        });
    }

    /**
     * @param  class-string<IssuedDocument>  $model
     * @param  list<int>  $ids
     */
    public function release(string $model, array $ids, User $actor): int
    {
        return $this->transition($model, $ids, $actor, DocumentEvent::Released, function (IssuedDocument $document) use ($actor): void {
            $document->status = IssuedDocumentStatus::Released;
            $document->released_at = CarbonImmutable::now();
            $document->released_by = $actor->id;
        });
    }

    /**
     * @param  class-string<IssuedDocument>  $model
     * @param  list<int>  $ids
     */
    public function cancel(string $model, array $ids, string $reason, User $actor): int
    {
        return $this->transition($model, $ids, $actor, DocumentEvent::Cancelled, function (IssuedDocument $document) use ($actor, $reason): void {
            $document->status = IssuedDocumentStatus::Cancelled;
            $document->cancelled_at = CarbonImmutable::now();
            $document->cancelled_by = $actor->id;
            $document->cancel_reason = $reason;
            $this->receipts->void($document);
        });
    }

    /**
     * @template TDocument of IssuedDocument
     *
     * @param  TDocument  $document
     * @return TDocument
     */
    public function recordPrint(IssuedDocument $document, User $actor): IssuedDocument
    {
        return DB::transaction(function () use ($document, $actor): IssuedDocument {
            /** @var TDocument $locked */
            $locked = $document::query()->lockForUpdate()->findOrFail($document->id);

            if ($locked->status === IssuedDocumentStatus::Cancelled) {
                throw BmsException::of(ErrorCode::DocumentCancelled, ['number' => $locked->documentNumber()]);
            }

            $locked->print_count++;
            $locked->last_printed_at = CarbonImmutable::now();
            $locked->save();

            $this->audit->record(
                $locked->auditAction(DocumentEvent::Printed),
                $locked,
                after: ['print_count' => $locked->print_count],
                barangayId: $locked->barangay_id,
                actorId: $actor->id,
            );

            return $locked;
        });
    }

    /**
     * @param  class-string<IssuedDocument>  $model
     * @param  list<int>  $ids
     * @param  callable(IssuedDocument): void  $apply
     */
    private function transition(string $model, array $ids, User $actor, DocumentEvent $event, callable $apply): int
    {
        return DB::transaction(function () use ($model, $ids, $actor, $event, $apply): int {
            /** @var Collection<int, IssuedDocument> $documents */
            $documents = $model::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();

            $blocked = $documents->reject(fn (IssuedDocument $document): bool => $document->isPending());
            if ($blocked->isNotEmpty() || $documents->count() !== count(array_unique($ids))) {
                throw BmsException::of(ErrorCode::DocumentsNotPending, [
                    'numbers' => $blocked->map(fn (IssuedDocument $document): string => $document->documentNumber())->implode(self::NUMBER_SEPARATOR),
                ]);
            }

            foreach ($documents as $document) {
                $before = ['status' => $document->status->value];
                $apply($document);
                $document->updated_by = $actor->id;
                $document->save();

                $this->audit->record(
                    $document->auditAction($event),
                    $document,
                    $before,
                    ['status' => $document->status->value, 'cancel_reason' => $document->cancel_reason],
                    $document->barangay_id,
                    $actor->id,
                );
            }

            return $documents->count();
        });
    }
}
