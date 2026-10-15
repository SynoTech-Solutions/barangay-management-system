<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Database\Factories\BankAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $barangay_id
 * @property string $account_number
 * @property string $account_name
 * @property string $depository_bank
 * @property string|null $branch_address
 * @property bool $is_active
 * @property int|null $updated_by
 */
class BankAccount extends Model
{
    /** @use HasFactory<BankAccountFactory> */
    use BelongsToBarangay, HasFactory;

    public const array DETAIL_ATTRIBUTES = ['account_number', 'account_name', 'depository_bank', 'branch_address'];

    protected $fillable = [...self::DETAIL_ATTRIBUTES, 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
