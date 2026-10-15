<?php

namespace App\Models;

use App\Enums\PaymentType;
use App\Enums\VoucherStatus;
use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\VoucherFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $barangay_id
 * @property string $voucher_number
 * @property int $budget_id
 * @property PaymentType $payment_type
 * @property VoucherStatus $status
 * @property CarbonImmutable $voucher_date
 * @property int|null $vendor_id
 * @property int|null $resident_id
 * @property string $payee_name
 * @property string|null $payee_address
 * @property string|null $payee_tin
 * @property string $particulars
 * @property string|null $responsibility_center
 * @property string|null $office_unit
 * @property string|null $treasurer_name
 * @property string|null $bookkeeper_name
 * @property string|null $punong_barangay_name
 * @property string|null $head_of_appropriations_name
 * @property int|null $prepared_by
 * @property CarbonImmutable $prepared_at
 * @property int|null $updated_by
 * @property int|null $verified_by
 * @property CarbonImmutable|null $verified_at
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $released_at
 * @property CarbonImmutable|null $closed_at
 * @property int|null $cancelled_by
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancel_reason
 * @property int $print_count
 * @property CarbonImmutable|null $last_printed_at
 * @property-read string|null $items_total
 * @property-read Budget|null $budget
 * @property-read Liquidation|null $liquidation
 * @property-read Check|null $activeCheck
 * @property-read Vendor|null $vendor
 * @property-read Resident|null $resident
 * @property-read User|null $preparer
 * @property-read User|null $verifier
 * @property-read User|null $approver
 * @property-read User|null $canceller
 * @property-read Collection<int, VoucherItem> $items
 */
class Voucher extends Model
{
    /** @use HasFactory<VoucherFactory> */
    use BelongsToBarangay, HasFactory;

    public const array DETAIL_ATTRIBUTES = ['budget_id', 'payment_type', 'voucher_date', 'vendor_id', 'resident_id', 'payee_name', 'payee_address', 'payee_tin', 'particulars', 'responsibility_center', 'office_unit'];

    protected $fillable = self::DETAIL_ATTRIBUTES;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_type' => PaymentType::class,
            'status' => VoucherStatus::class,
            'voucher_date' => 'date',
            'prepared_at' => 'datetime',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'released_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_printed_at' => 'datetime',
        ];
    }

    public function isCashAdvance(): bool
    {
        return $this->payment_type === PaymentType::CashAdvance;
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return HasMany<VoucherItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(VoucherItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<Check, $this>
     */
    public function checks(): HasMany
    {
        return $this->hasMany(Check::class);
    }

    /**
     * @return HasOne<Check, $this>
     */
    public function activeCheck(): HasOne
    {
        return $this->hasOne(Check::class)->whereNull('cancelled_at');
    }

    /**
     * @return HasOne<Liquidation, $this>
     */
    public function liquidation(): HasOne
    {
        return $this->hasOne(Liquidation::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
