<?php

namespace App\Models;

use App\Enums\SmsEncoding;
use App\Enums\SmsStatus;
use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\SmsMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int|null $sms_batch_id
 * @property int|null $resident_id
 * @property string|null $recipient
 * @property string|null $body
 * @property SmsEncoding $encoding
 * @property int $segments
 * @property SmsStatus $status
 * @property int $attempts
 * @property string|null $last_error_code
 * @property string|null $provider_message_id
 * @property int|null $queued_by
 * @property CarbonImmutable $queued_at
 * @property CarbonImmutable|null $sending_started_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $cancelled_by
 * @property CarbonImmutable|null $pruned_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SmsBatch|null $batch
 * @property-read Resident|null $resident
 * @property-read User|null $queuedBy
 * @property-read User|null $cancelledBy
 */
class SmsMessage extends Model
{
    /** @use HasFactory<SmsMessageFactory> */
    use BelongsToBarangay, HasFactory;

    protected $fillable = ['sms_batch_id', 'resident_id', 'recipient', 'body', 'encoding', 'segments'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'encoding' => SmsEncoding::class,
            'status' => SmsStatus::class,
            'queued_at' => 'datetime',
            'sending_started_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'pruned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SmsBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(SmsBatch::class, 'sms_batch_id');
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function queuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'queued_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
