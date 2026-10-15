<?php

namespace App\Models;

use App\Enums\SmsSource;
use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\SmsBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $barangay_id
 * @property SmsSource $source
 * @property int|null $sms_group_id
 * @property int $recipient_count
 * @property int $skipped_count
 * @property int $segments
 * @property int|null $queued_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SmsGroup|null $group
 */
class SmsBatch extends Model
{
    /** @use HasFactory<SmsBatchFactory> */
    use BelongsToBarangay, HasFactory;

    protected $fillable = ['source', 'sms_group_id', 'recipient_count', 'skipped_count', 'segments'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['source' => SmsSource::class];
    }

    /**
     * @return BelongsTo<SmsGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SmsGroup::class, 'sms_group_id');
    }
}
