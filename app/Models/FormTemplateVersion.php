<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\FormTemplateVersionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $form_template_id
 * @property int $version
 * @property string|null $body
 * @property string|null $pdf_path
 * @property string|null $pdf_file_name
 * @property int|null $pdf_size_bytes
 * @property string|null $pdf_sha256
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 * @property-read FormTemplate|null $template
 * @property-read User|null $creator
 * @property-read Collection<int, FormTemplateField> $fields
 * @property-read Collection<int, FormPdfMapping> $mappings
 */
class FormTemplateVersion extends Model
{
    /** @use HasFactory<FormTemplateVersionFactory> */
    use BelongsToBarangay, HasFactory;

    public const null UPDATED_AT = null;

    protected $fillable = ['version', 'body', 'pdf_path', 'pdf_file_name', 'pdf_size_bytes', 'pdf_sha256'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<FormTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<FormTemplateField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(FormTemplateField::class)->orderBy('position');
    }

    /**
     * @return HasMany<FormPdfMapping, $this>
     */
    public function mappings(): HasMany
    {
        return $this->hasMany(FormPdfMapping::class)->orderBy('id');
    }
}
