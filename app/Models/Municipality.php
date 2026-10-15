<?php

namespace App\Models;

use Database\Factories\MunicipalityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $region_id
 * @property int|null $province_id
 * @property string $psgc_code
 * @property string $name
 * @property bool $is_city
 * @property-read Region $region
 * @property-read Province|null $province
 */
class Municipality extends Model
{
    /** @use HasFactory<MunicipalityFactory> */
    use HasFactory;

    protected $fillable = ['region_id', 'province_id', 'psgc_code', 'name', 'is_city'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_city' => 'boolean'];
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }
}
