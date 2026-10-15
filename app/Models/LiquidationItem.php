<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Database\Factories\LiquidationItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $liquidation_id
 * @property int $voucher_item_id
 * @property string $actual_amount
 * @property-read VoucherItem|null $voucherItem
 */
class LiquidationItem extends Model
{
    /** @use HasFactory<LiquidationItemFactory> */
    use BelongsToBarangay, HasFactory;

    protected $fillable = ['liquidation_id', 'voucher_item_id', 'actual_amount'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['actual_amount' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<VoucherItem, $this>
     */
    public function voucherItem(): BelongsTo
    {
        return $this->belongsTo(VoucherItem::class);
    }
}
