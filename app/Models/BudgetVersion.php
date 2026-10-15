<?php

namespace App\Models;

use App\Domain\Money\Centavos;
use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\BudgetVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $budget_id
 * @property int $number
 * @property string|null $remarks
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Budget|null $budget
 * @property-read User|null $creator
 */
class BudgetVersion extends Model
{
    /** @use HasFactory<BudgetVersionFactory> */
    use BelongsToBarangay, HasFactory;

    public const int ORIGINAL = 1;

    protected $fillable = ['budget_id', 'number', 'remarks'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function isRevision(): bool
    {
        return $this->number > self::ORIGINAL;
    }

    /**
     * @return array<int, int>
     */
    public function amounts(): array
    {
        return $this->lines()->pluck('amount', 'account_id')
            ->mapWithKeys(fn (mixed $amount, int $accountId): array => [$accountId => Centavos::fromDecimal((string) $amount)])
            ->all();
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return HasMany<BudgetLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
