<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $barangay_id
 * @property string $flag
 * @property bool $enabled
 * @property string|null $reason
 * @property int|null $updated_by
 */
class FeatureOverride extends Model
{
    protected $fillable = ['barangay_id', 'flag', 'enabled', 'reason', 'updated_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
