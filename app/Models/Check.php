<?php

namespace App\Models;

use App\Enums\CheckStatus;
use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\CheckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int|null $voucher_id
 * @property int $bank_account_id
 * @property int|null $vendor_id
 * @property string $payee_name
 * @property string $check_number
 * @property CarbonImmutable $check_date
 * @property string $amount
 * @property string|null $remarks
 * @property CheckStatus $status
 * @property int|null $issued_by
 * @property CarbonImmutable $issued_at
 * @property int|null $updated_by
 * @property int|null $released_by
 * @property CarbonImmutable|null $released_at
 * @property string|null $received_by_name
 * @property int|null $cancelled_by
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancel_reason
 * @property int $print_count
 * @property CarbonImmutable|null $last_printed_at
 * @property-read Voucher|null $voucher
 * @property-read BankAccount|null $bankAccount
 * @property-read Vendor|null $vendor
 * @property-read User|null $issuer
 * @property-read User|null $releaser
 * @property-read User|null $canceller
 */
class Check extends Model
{
    /** @use HasFactory<CheckFactory> */
    use BelongsToBarangay, HasFactory;

    public const string NUMBER_INDEX = 'checks_number_per_bank_unique';

    public const string VOUCHER_INDEX = 'checks_one_active_per_voucher';

    public const array DETAIL_ATTRIBUTES = ['bank_account_id', 'check_number', 'check_date', 'amount', 'remarks'];

    protected $fillable = [...self::DETAIL_ATTRIBUTES, 'voucher_id', 'vendor_id', 'payee_name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CheckStatus::class,
            'check_date' => 'date',
            'amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'released_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_printed_at' => 'datetime',
        ];
    }

    public function isForRelease(): bool
    {
        return $this->status === CheckStatus::ForRelease;
    }

    public function isDirect(): bool
    {
        return $this->voucher_id === null;
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
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
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
