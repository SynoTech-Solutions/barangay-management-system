<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Database\Factories\FormPicklistItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $form_picklist_id
 * @property string $label
 * @property bool $is_active
 */
class FormPicklistItem extends Model
{
    /** @use HasFactory<FormPicklistItemFactory> */
    use BelongsToBarangay, HasFactory;

    protected $fillable = ['label'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
