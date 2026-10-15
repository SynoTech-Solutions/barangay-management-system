<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $barangay_id
 * @property int $resident_id
 * @property int $sector_id
 */
class ResidentSector extends Pivot
{
    use BelongsToBarangay;

    protected $table = 'resident_sector';

    public $incrementing = false;
}
