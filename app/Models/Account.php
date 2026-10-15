<?php

namespace App\Models;

use App\Enums\AccountGroupType;
use App\Enums\AccountKind;
use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int|null $parent_id
 * @property int|null $group_id
 * @property AccountKind $kind
 * @property AccountGroupType|null $group_type
 * @property string $code
 * @property string $name
 * @property int $level
 * @property string|null $remarks
 * @property bool $is_locked
 * @property bool $is_active
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Account|null $parent
 * @property-read Account|null $group
 * @property-read int|null $children_count
 */
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use BelongsToBarangay, HasFactory;

    public const array AUDITED_ATTRIBUTES = ['kind', 'group_type', 'code', 'name', 'parent_id', 'remarks', 'is_locked', 'is_active'];

    protected $fillable = ['parent_id', 'group_id', 'kind', 'group_type', 'code', 'name', 'level', 'remarks', 'is_locked', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AccountKind::class,
            'group_type' => AccountGroupType::class,
            'is_locked' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function isGroup(): bool
    {
        return $this->kind === AccountKind::Group;
    }

    public function groupId(): int
    {
        return $this->group_id ?? $this->id;
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(self::class, 'group_id');
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<BudgetLine, $this>
     */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }
}
