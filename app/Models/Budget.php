<?php

namespace App\Models;

use App\Enums\BudgetStatus;
use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $fiscal_year
 * @property string $title
 * @property BudgetStatus $status
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $approver
 */
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use BelongsToBarangay, HasFactory;

    protected $fillable = ['fiscal_year', 'title'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BudgetStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === BudgetStatus::Draft;
    }

    public function isApproved(): bool
    {
        return $this->status === BudgetStatus::Approved;
    }

    public function latestVersion(): BudgetVersion
    {
        return $this->versions()->orderByDesc('number')->firstOrFail();
    }

    public function effectiveVersion(): ?BudgetVersion
    {
        return $this->versions()->whereNotNull('approved_at')->orderByDesc('number')->first();
    }

    /**
     * @return HasMany<BudgetVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(BudgetVersion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
