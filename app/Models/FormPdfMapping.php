<?php

namespace App\Models;

use App\Enums\PdfFieldType;
use App\Models\Concerns\BelongsToBarangay;
use Database\Factories\FormPdfMappingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $form_template_version_id
 * @property string $pdf_field
 * @property PdfFieldType $pdf_field_type
 * @property string|null $pdf_on_state
 * @property string|null $token
 * @property string|null $checked_value
 * @property string|null $unchecked_value
 */
class FormPdfMapping extends Model
{
    /** @use HasFactory<FormPdfMappingFactory> */
    use BelongsToBarangay, HasFactory;

    public $timestamps = false;

    protected $fillable = ['pdf_field', 'pdf_field_type', 'pdf_on_state', 'token', 'checked_value', 'unchecked_value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['pdf_field_type' => PdfFieldType::class];
    }
}
