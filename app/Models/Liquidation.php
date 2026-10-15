<?php

namespace App\Models;

use App\Enums\LiquidationStatus;
use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\LiquidationFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $voucher_id
 * @property string $liquidation_number
 * @property CarbonImmutable $liquidated_on
 * @property string|null $or_number
 * @property CarbonImmutable|null $or_date
 * @property LiquidationStatus $status
 * @property int|null $recorded_by
 * @property CarbonImmutable $recorded_at
 * @property int|null $updated_by
 * @property int|null $cleared_by
 * @property CarbonImmutable|null $cleared_at
 * @property int $print_count
 * @property CarbonImmutable|null $last_printed_at
 * @property-read Voucher|null $voucher
 * @property-read User|null $recorder
 * @property-read User|null $clearer
 * @property-read Collection<int, LiquidationItem> $items
 */
class Liquidation extends Model
{
    /** @use HasFactory<LiquidationFactory> */
    use BelongsToBarangay, HasFactory;

    protected $fillable = ['voucher_id', 'liquidated_on', 'or_number', 'or_date'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LiquidationStatus::class,
            'liquidated_on' => 'date',
            'or_date' => 'date',
            'recorded_at' => 'datetime',
            'cleared_at' => 'datetime',
            'last_printed_at' => 'datetime',
        ];
    }

    public function isCleared(): bool
    {
        return $this->status === LiquidationStatus::Cleared;
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * @return HasMany<LiquidationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LiquidationItem::class);
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
    public function clearer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }
}
