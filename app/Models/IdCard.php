<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\DocumentEvent;
use Database\Factories\IdCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $resident_id
 * @property string $card_number
 * @property-read Resident|null $resident
 */
class IdCard extends IssuedDocument
{
    /** @use HasFactory<IdCardFactory> */
    use HasFactory;

    public const array DETAIL_ATTRIBUTES = ['ctc_number', 'ctc_place', 'ctc_date', 'or_number', 'amount'];

    protected $fillable = self::DETAIL_ATTRIBUTES;

    public function documentNumber(): string
    {
        return $this->card_number;
    }

    public function auditAction(DocumentEvent $event): AuditAction
    {
        return match ($event) {
            DocumentEvent::Issued => AuditAction::IdCardIssued,
            DocumentEvent::Updated => AuditAction::IdCardUpdated,
            DocumentEvent::Released => AuditAction::IdCardReleased,
            DocumentEvent::Cancelled => AuditAction::IdCardCancelled,
            DocumentEvent::Printed => AuditAction::IdCardPrinted,
        };
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }
}
