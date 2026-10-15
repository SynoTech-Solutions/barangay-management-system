<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\DocumentEvent;
use Database\Factories\CedulaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $resident_id
 * @property string $cedula_number
 * @property string|null $form_number
 * @property int $tax_year
 * @property string $place_of_issue
 * @property bool $is_exempt
 * @property string $business_gross_receipts
 * @property string $salaries
 * @property string $real_property_income
 * @property string $basic_tax
 * @property string $business_tax
 * @property string $salary_tax
 * @property string $real_property_tax
 * @property string $additional_tax
 * @property string $interest
 * @property array<string, mixed> $breakdown
 * @property-read Resident|null $resident
 */
class Cedula extends IssuedDocument
{
    /** @use HasFactory<CedulaFactory> */
    use HasFactory;

    public const array INPUT_ATTRIBUTES = ['is_exempt', 'business_gross_receipts', 'salaries', 'real_property_income'];

    protected $fillable = ['form_number', ...self::INPUT_ATTRIBUTES];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'is_exempt' => 'boolean',
            'business_gross_receipts' => 'decimal:2',
            'salaries' => 'decimal:2',
            'real_property_income' => 'decimal:2',
            'basic_tax' => 'decimal:2',
            'business_tax' => 'decimal:2',
            'salary_tax' => 'decimal:2',
            'real_property_tax' => 'decimal:2',
            'additional_tax' => 'decimal:2',
            'interest' => 'decimal:2',
            'breakdown' => 'array',
        ];
    }

    public function documentNumber(): string
    {
        return $this->cedula_number;
    }

    public function referenceNumber(): string
    {
        return $this->form_number ?? $this->cedula_number;
    }

    public function auditAction(DocumentEvent $event): AuditAction
    {
        return match ($event) {
            DocumentEvent::Issued => AuditAction::CedulaIssued,
            DocumentEvent::Updated => AuditAction::Updated,
            DocumentEvent::Released => AuditAction::CedulaReleased,
            DocumentEvent::Cancelled => AuditAction::CedulaCancelled,
            DocumentEvent::Printed => AuditAction::CedulaPrinted,
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
