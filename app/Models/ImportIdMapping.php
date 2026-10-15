<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $barangay_id
 * @property string $entity
 * @property string $source_key
 * @property string $target_table
 * @property int $target_id
 * @property int|null $import_run_id
 * @property CarbonImmutable $created_at
 */
class ImportIdMapping extends Model
{
    use BelongsToBarangay;

    public const string TABLE = 'import_id_map';

    public const UPDATED_AT = null;

    protected $table = self::TABLE;

    protected $fillable = ['entity', 'source_key', 'target_table', 'target_id', 'import_run_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
