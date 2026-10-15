<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\OtherCollectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $barangay_id
 * @property string $collection_number
 * @property int $collection_type_id
 * @property int|null $resident_id
 * @property string $payer_name
 * @property string $or_number
 * @property string $amount
 * @property CarbonImmutable $paid_on
 * @property string|null $remarks
 * @property int|null $recorded_by
 * @property CarbonImmutable $recorded_at
 * @property int|null $updated_by
 * @property int|null $cancelled_by
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancel_reason
 * @property-read CollectionType|null $collectionType
 * @property-read Resident|null $resident
 * @property-read User|null $recorder
 * @property-read User|null $canceller
 */
class OtherCollection extends Model
{
    /** @use HasFactory<OtherCollectionFactory> */
    use BelongsToBarangay, HasFactory;

    public const array DETAIL_ATTRIBUTES = ['collection_type_id', 'resident_id', 'payer_name', 'or_number', 'amount', 'paid_on', 'remarks'];

    protected $fillable = self::DETAIL_ATTRIBUTES;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'recorded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeNotCancelled(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('cancelled_at'));
    }

    /**
     * @return BelongsTo<CollectionType, $this>
     */
    public function collectionType(): BelongsTo
    {
        return $this->belongsTo(CollectionType::class);
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
