<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Database\Factories\ColorCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $barangay_id
 * @property string $name
 * @property string $color_hex
 * @property bool $is_active
 * @property-read int|null $tags_count
 */
class ColorCategory extends Model
{
    /** @use HasFactory<ColorCategoryFactory> */
    use BelongsToBarangay, HasFactory;

    public const string COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    protected $fillable = ['name', 'color_hex', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<ResidentPoliticalTag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(ResidentPoliticalTag::class);
    }
}
