<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\ResidentWorkHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $resident_id
 * @property int $company_id
 * @property int $position_id
 * @property int $employment_status_id
 * @property CarbonImmutable|null $started_on
 * @property CarbonImmutable|null $ended_on
 * @property bool $is_current
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property-read LookupValue $company
 * @property-read LookupValue $position
 * @property-read LookupValue $employmentStatus
 */
class ResidentWorkHistory extends Model
{
    /** @use HasFactory<ResidentWorkHistoryFactory> */
    use BelongsToBarangay, HasFactory, SoftDeletes;

    public const array AUDITED_ATTRIBUTES = ['company_id', 'position_id', 'employment_status_id', 'started_on', 'ended_on', 'is_current'];

    protected $table = 'resident_work_histories';

    protected $fillable = ['company_id', 'position_id', 'employment_status_id', 'started_on', 'ended_on', 'is_current'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['started_on' => 'date', 'ended_on' => 'date', 'is_current' => 'boolean'];
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'company_id');
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'position_id');
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function employmentStatus(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'employment_status_id');
    }
}
