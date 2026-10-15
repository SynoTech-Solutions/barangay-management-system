<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\DocumentEvent;
use Database\Factories\IndigencyCertificateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $resident_id
 * @property string $certificate_number
 * @property int $purpose_id
 * @property-read Resident|null $resident
 * @property-read LookupValue|null $purpose
 */
class IndigencyCertificate extends IssuedDocument
{
    /** @use HasFactory<IndigencyCertificateFactory> */
    use HasFactory;

    public const array DETAIL_ATTRIBUTES = ['purpose_id'];

    protected $fillable = self::DETAIL_ATTRIBUTES;

    public function documentNumber(): string
    {
        return $this->certificate_number;
    }

    public function auditAction(DocumentEvent $event): AuditAction
    {
        return match ($event) {
            DocumentEvent::Issued => AuditAction::IndigencyIssued,
            DocumentEvent::Updated => AuditAction::IndigencyUpdated,
            DocumentEvent::Released => AuditAction::IndigencyReleased,
            DocumentEvent::Cancelled => AuditAction::IndigencyCancelled,
            DocumentEvent::Printed => AuditAction::IndigencyPrinted,
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
    public function purpose(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'purpose_id');
    }
}
