<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\DocumentEvent;
use Carbon\CarbonImmutable;
use Database\Factories\BusinessClearanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $business_id
 * @property string $clearance_number
 * @property string $control_number
 * @property string $business_name
 * @property string|null $business_address
 * @property int|null $owner_resident_id
 * @property string $owner_name
 * @property string $doc_stamp
 * @property CarbonImmutable $expires_on
 * @property-read Business|null $business
 * @property-read Resident|null $owner
 */
class BusinessClearance extends IssuedDocument
{
    /** @use HasFactory<BusinessClearanceFactory> */
    use HasFactory;

    public const array DETAIL_ATTRIBUTES = ['or_number', 'amount', 'doc_stamp', 'ctc_number', 'ctc_place', 'ctc_date'];

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
            DocumentEvent::Issued => AuditAction::BusinessClearanceIssued,
            DocumentEvent::Updated => AuditAction::BusinessClearanceUpdated,
            DocumentEvent::Released => AuditAction::BusinessClearanceReleased,
            DocumentEvent::Cancelled => AuditAction::BusinessClearanceCancelled,
            DocumentEvent::Printed => AuditAction::BusinessClearancePrinted,
        };
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'owner_resident_id')->withTrashed();
    }
}
