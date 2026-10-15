<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\DocumentEvent;
use Carbon\CarbonImmutable;
use Database\Factories\BarangayClearanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $resident_id
 * @property string $clearance_number
 * @property string $control_number
 * @property int $clearance_type_id
 * @property int $purpose_id
 * @property string $doc_stamp
 * @property CarbonImmutable $expires_on
 * @property-read Resident|null $resident
 * @property-read LookupValue|null $clearanceType
 * @property-read LookupValue|null $purpose
 */
class BarangayClearance extends IssuedDocument
{
    /** @use HasFactory<BarangayClearanceFactory> */
    use HasFactory;

    public const array DETAIL_ATTRIBUTES = ['clearance_type_id', 'purpose_id', 'or_number', 'amount', 'doc_stamp', 'ctc_number', 'ctc_place', 'ctc_date'];

    protected $fillable = self::DETAIL_ATTRIBUTES;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [...parent::casts(), 'doc_stamp' => 'decimal:2', 'expires_on' => 'date'];
    }

    public function documentNumber(): string
    {
        return $this->clearance_number;
    }

    public function auditAction(DocumentEvent $event): AuditAction
    {
        return match ($event) {
            DocumentEvent::Issued => AuditAction::ClearanceIssued,
            DocumentEvent::Updated => AuditAction::ClearanceUpdated,
            DocumentEvent::Released => AuditAction::ClearanceReleased,
            DocumentEvent::Cancelled => AuditAction::ClearanceCancelled,
            DocumentEvent::Printed => AuditAction::ClearancePrinted,
        };
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function clearanceType(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'clearance_type_id');
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function purpose(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'purpose_id');
    }
}
