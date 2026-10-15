<?php

namespace App\Models;

use App\Enums\ReceiptDocumentType;
use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $barangay_id
 * @property string $or_number
 * @property ReceiptDocumentType $document_type
 * @property int $document_id
 * @property CarbonImmutable|null $cancelled_at
 */
class OfficialReceipt extends Model
{
    use BelongsToBarangay;

    public const string ACTIVE_INDEX = 'official_receipts_active_or_number_unique';

    protected $fillable = ['barangay_id', 'or_number', 'document_type', 'document_id', 'cancelled_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => ReceiptDocumentType::class,
            'cancelled_at' => 'datetime',
        ];
    }

    public static function typeFor(?Model $document): ?ReceiptDocumentType
    {
        return match (true) {
            $document instanceof BarangayClearance => ReceiptDocumentType::BarangayClearance,
            $document instanceof BusinessClearance => ReceiptDocumentType::BusinessClearance,
            $document instanceof IdCard => ReceiptDocumentType::IdCard,
            $document instanceof OtherCollection => ReceiptDocumentType::OtherCollection,
            $document instanceof Liquidation => ReceiptDocumentType::LiquidationRefund,
            $document instanceof FormTransaction => ReceiptDocumentType::FormTransaction,
            default => null,
        };
    }
}
