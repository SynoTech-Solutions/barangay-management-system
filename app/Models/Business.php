<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $barangay_id
 * @property string $business_number
 * @property string $name
 * @property int|null $owner_resident_id
 * @property string|null $owner_name
 * @property int $category_id
 * @property int $nature_id
 * @property int $ownership_type_id
 * @property int|null $purok_id
 * @property string|null $street_address
 * @property string|null $plate_number
 * @property CarbonImmutable|null $operating_since
 * @property string|null $capitalization
 * @property string|null $gross_receipts
 * @property BusinessStatus $status
 * @property int|null $registered_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Resident|null $owner
 * @property-read LookupValue|null $category
 * @property-read LookupValue|null $nature
 * @property-read LookupValue|null $ownershipType
 * @property-read Purok|null $purok
 * @property-read User|null $registrar
 */
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use BelongsToBarangay, HasFactory;

    public const array DETAIL_ATTRIBUTES = [
        'name', 'owner_resident_id', 'owner_name', 'category_id', 'nature_id', 'ownership_type_id',
        'purok_id', 'street_address', 'plate_number', 'operating_since', 'capitalization', 'gross_receipts',
    ];

    public const array RELATIONS = [
        'owner:id,full_name,resident_number,deleted_at',
        'category:id,label',
        'nature:id,label',
        'ownershipType:id,label',
        'purok:id,name',
    ];

    protected $fillable = self::DETAIL_ATTRIBUTES;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BusinessStatus::class,
            'operating_since' => 'date',
            'capitalization' => 'decimal:2',
            'gross_receipts' => 'decimal:2',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === BusinessStatus::Active;
    }

    public function ownerDisplayName(): string
    {
        return $this->owner->full_name ?? $this->owner_name ?? '';
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), BusinessStatus::Active->value);
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'owner_resident_id')->withTrashed();
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'category_id');
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function nature(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'nature_id');
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function ownershipType(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'ownership_type_id');
    }

    /**
     * @return BelongsTo<Purok, $this>
     */
    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * @return HasMany<BusinessClearance, $this>
     */
    public function clearances(): HasMany
    {
        return $this->hasMany(BusinessClearance::class);
    }
}
