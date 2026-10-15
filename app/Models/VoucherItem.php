<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Database\Factories\VoucherItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $voucher_id
 * @property int $account_id
 * @property string $description
 * @property string $amount
 * @property int $position
 * @property-read Account|null $account
 * @property-read Voucher|null $voucher
 */
class VoucherItem extends Model
{
    /** @use HasFactory<VoucherItemFactory> */
    use BelongsToBarangay, HasFactory;

    protected $fillable = ['voucher_id', 'account_id', 'description', 'amount', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
