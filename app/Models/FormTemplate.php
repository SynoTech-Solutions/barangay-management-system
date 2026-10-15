<?php

namespace App\Models;

use App\Enums\FormTemplateKind;
use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\FormTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $barangay_id
 * @property string $template_number
 * @property int $type_id
 * @property string $name
 * @property FormTemplateKind $kind
 * @property bool $is_active
 * @property int|null $current_version_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read LookupValue|null $type
 * @property-read FormTemplateVersion|null $currentVersion
 */
class FormTemplate extends Model
{
    /** @use HasFactory<FormTemplateFactory> */
    use BelongsToBarangay, HasFactory;

    protected $fillable = ['type_id', 'name', 'kind'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FormTemplateKind::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'type_id');
    }

    /**
     * @return BelongsTo<FormTemplateVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(FormTemplateVersion::class, 'current_version_id');
    }

    /**
     * @return HasMany<FormTemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(FormTemplateVersion::class);
    }
}
