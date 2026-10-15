<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Database\Factories\FormPicklistFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $barangay_id
 * @property string $name
 * @property bool $is_active
 * @property int|null $updated_by
 * @property-read Collection<int, FormPicklistItem> $items
 */
class FormPicklist extends Model
{
    /** @use HasFactory<FormPicklistFactory> */
    use BelongsToBarangay, HasFactory;

    protected $fillable = ['name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<FormPicklistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(FormPicklistItem::class)->orderBy('label');
    }
}
