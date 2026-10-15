<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\SmsGroupMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $sms_group_id
 * @property int|null $resident_id
 * @property string|null $mobile_number
 * @property string|null $label
 * @property int|null $added_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SmsGroup $group
 * @property-read Resident|null $resident
 */
class SmsGroupMember extends Model
{
    /** @use HasFactory<SmsGroupMemberFactory> */
    use BelongsToBarangay, HasFactory;

    protected $fillable = ['sms_group_id', 'resident_id', 'mobile_number', 'label'];

    /**
     * @return BelongsTo<SmsGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SmsGroup::class, 'sms_group_id');
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }
}
