<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Database\Factories\CedulaTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $barangay_id
 * @property string $name
 * @property bool $is_exempt
 * @property string $business_gross_receipts
 * @property string $salaries
 * @property string $real_property_income
 * @property bool $is_active
 * @property int|null $updated_by
 */
class CedulaTemplate extends Model
{
    /** @use HasFactory<CedulaTemplateFactory> */
    use BelongsToBarangay, HasFactory;

    public const array DETAIL_ATTRIBUTES = ['name', 'is_exempt', 'business_gross_receipts', 'salaries', 'real_property_income'];

    protected $fillable = self::DETAIL_ATTRIBUTES;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_exempt' => 'boolean',
            'is_active' => 'boolean',
            'business_gross_receipts' => 'decimal:2',
            'salaries' => 'decimal:2',
            'real_property_income' => 'decimal:2',
        ];
    }
}
