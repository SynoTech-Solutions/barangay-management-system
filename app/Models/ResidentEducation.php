<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\ResidentEducationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $resident_id
 * @property int $school_id
 * @property int $course_id
 * @property CarbonImmutable|null $started_on
 * @property CarbonImmutable|null $ended_on
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property-read LookupValue $school
 * @property-read LookupValue $course
 */
class ResidentEducation extends Model
{
    /** @use HasFactory<ResidentEducationFactory> */
    use BelongsToBarangay, HasFactory, SoftDeletes;

    public const array AUDITED_ATTRIBUTES = ['school_id', 'course_id', 'started_on', 'ended_on'];

    protected $table = 'resident_educations';

    protected $fillable = ['school_id', 'course_id', 'started_on', 'ended_on'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['started_on' => 'date', 'ended_on' => 'date'];
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
    public function school(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'school_id');
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'course_id');
    }
}
