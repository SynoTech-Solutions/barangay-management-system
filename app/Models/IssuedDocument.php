<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\DocumentEvent;
use App\Enums\IssuedDocumentStatus;
use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $barangay_id
 * @property IssuedDocumentStatus $status
 * @property string|null $or_number
 * @property string $amount
 * @property string|null $ctc_number
 * @property string|null $ctc_place
 * @property CarbonImmutable|null $ctc_date
 * @property string|null $verified_by_name
 * @property string|null $verified_by_title
 * @property string|null $certified_by_name
 * @property string|null $certified_by_title
 * @property int|null $issued_by
 * @property CarbonImmutable $issued_at
 * @property int|null $updated_by
 * @property int|null $released_by
 * @property CarbonImmutable|null $released_at
 * @property int|null $cancelled_by
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancel_reason
 * @property int $print_count
 * @property CarbonImmutable|null $last_printed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $issuer
 * @property-read User|null $releaser
 * @property-read User|null $canceller
 */
abstract class IssuedDocument extends Model
{
    use BelongsToBarangay;

    public const array LIFECYCLE_RELATIONS = ['issuer:id,name', 'releaser:id,name', 'canceller:id,name'];

    abstract public function documentNumber(): string;

    abstract public function auditAction(DocumentEvent $event): AuditAction;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IssuedDocumentStatus::class,
            'ctc_date' => 'date',
            'amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'released_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_printed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === IssuedDocumentStatus::Issued;
    }

    public function isReprint(): bool
    {
        return $this->print_count > 1;
    }

    public function snapshotSignatories(?Signatory $verifier, ?Signatory $certifier): void
    {
        $this->verified_by_name = $verifier?->name;
        $this->verified_by_title = $verifier?->title;
        $this->certified_by_name = $certifier?->name;
        $this->certified_by_title = $certifier?->title;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeNotCancelled(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), '<>', IssuedDocumentStatus::Cancelled->value);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
