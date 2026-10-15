<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\DocumentEvent;
use Database\Factories\FormTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $transaction_number
 * @property int $form_template_id
 * @property int $form_template_version_id
 * @property int $resident_id
 * @property array<string, mixed> $snapshot
 * @property-read Resident|null $resident
 * @property-read FormTemplate|null $template
 * @property-read FormTemplateVersion|null $version
 */
class FormTransaction extends IssuedDocument
{
    /** @use HasFactory<FormTransactionFactory> */
    use HasFactory;

    public const array DETAIL_ATTRIBUTES = ['or_number', 'amount'];

    protected $fillable = self::DETAIL_ATTRIBUTES;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [...parent::casts(), 'snapshot' => 'array'];
    }

    public function documentNumber(): string
    {
        return $this->transaction_number;
    }

    public function auditAction(DocumentEvent $event): AuditAction
    {
        return match ($event) {
            DocumentEvent::Issued => AuditAction::FormTransactionIssued,
            DocumentEvent::Updated => AuditAction::FormTransactionUpdated,
            DocumentEvent::Released => AuditAction::FormTransactionReleased,
            DocumentEvent::Cancelled => AuditAction::FormTransactionCancelled,
            DocumentEvent::Printed => AuditAction::FormTransactionPrinted,
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
     * @return BelongsTo<FormTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }

    /**
     * @return BelongsTo<FormTemplateVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(FormTemplateVersion::class, 'form_template_version_id');
    }
}
