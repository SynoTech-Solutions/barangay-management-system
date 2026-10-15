<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $blotter_id
 * @property string $path
 * @property string $mime_type
 * @property int $size_bytes
 * @property int|null $uploaded_by
 * @property int|null $deleted_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $deleted_at
 */
class BlotterPhoto extends Model
{
    use BelongsToBarangay, SoftDeletes;

    protected $fillable = ['path', 'mime_type', 'size_bytes'];
}
