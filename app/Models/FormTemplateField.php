<?php

namespace App\Models;

use App\Enums\FormInputType;
use App\Models\Concerns\BelongsToBarangay;
use Database\Factories\FormTemplateFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $form_template_version_id
 * @property string $key
 * @property string $label
 * @property FormInputType $input_type
 * @property bool $is_required
 * @property int $position
 * @property int|null $form_picklist_id
 * @property-read FormPicklist|null $picklist
 */
class FormTemplateField extends Model
{
    /** @use HasFactory<FormTemplateFieldFactory> */
    use BelongsToBarangay, HasFactory;

    public $timestamps = false;

    protected $fillable = ['key', 'label', 'input_type', 'is_required', 'position', 'form_picklist_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_type' => FormInputType::class,
            'is_required' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<FormPicklist, $this>
     */
    public function picklist(): BelongsTo
    {
        return $this->belongsTo(FormPicklist::class, 'form_picklist_id');
    }
}
