<?php

namespace App\Models;

use App\Enums\ReportDataset;
use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\SavedReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $barangay_id
 * @property string $name
 * @property ReportDataset $dataset
 * @property array<mixed> $definition
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SavedReport extends Model
{
    /** @use HasFactory<SavedReportFactory> */
    use BelongsToBarangay, HasFactory;

    protected $fillable = ['name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dataset' => ReportDataset::class,
            'definition' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
