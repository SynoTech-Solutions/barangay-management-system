<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $resident_id
 * @property int $color_category_id
 * @property int|null $tagged_by
 * @property-read ColorCategory $category
 */
class ResidentPoliticalTag extends Model
{
    use BelongsToBarangay;

    protected $fillable = ['resident_id', 'color_category_id', 'tagged_by'];

    /**
     * @return BelongsTo<ColorCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ColorCategory::class, 'color_category_id');
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
