<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Database\Factories\BudgetLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $budget_version_id
 * @property int $account_id
 * @property string $amount
 * @property string|null $remarks
 * @property-read Account|null $account
 */
class BudgetLine extends Model
{
    /** @use HasFactory<BudgetLineFactory> */
    use BelongsToBarangay, HasFactory;

    protected $fillable = ['budget_version_id', 'account_id', 'amount', 'remarks'];

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
}
