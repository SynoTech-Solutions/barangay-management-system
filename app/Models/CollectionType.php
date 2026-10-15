<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Database\Factories\CollectionTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $barangay_id
 * @property string $name
 * @property bool $is_active
 * @property int|null $updated_by
 * @property-read int|null $collections_count
 */
class CollectionType extends Model
{
    /** @use HasFactory<CollectionTypeFactory> */
    use BelongsToBarangay, HasFactory;

    protected $fillable = ['name', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<OtherCollection, $this>
     */
    public function collections(): HasMany
    {
        return $this->hasMany(OtherCollection::class);
    }
}
