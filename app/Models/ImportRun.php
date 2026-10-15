<?php

namespace App\Models;

use App\Enums\ImportRunStatus;
use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $barangay_id
 * @property ImportRunStatus $status
 * @property array<string, mixed> $options
 * @property array<string, mixed>|null $report
 * @property string|null $problems_path
 * @property int|null $operator_id
 * @property string|null $operator_name
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 */
class ImportRun extends Model
{
    use BelongsToBarangay;

    public const string TABLE = 'import_runs';

    protected $table = self::TABLE;

    protected $fillable = ['status', 'options', 'report', 'problems_path', 'operator_id', 'operator_name', 'started_at', 'finished_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportRunStatus::class,
            'options' => 'array',
            'report' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
