<?php

namespace App\Models;

use App\Enums\BarangayStatus;
use Database\Factories\BarangayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $municipality_id
 * @property string $psgc_code
 * @property string $name
 * @property BarangayStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Municipality $municipality
 * @property-read BarangayProfile|null $profile
 */
class Barangay extends Model
{
    /** @use HasFactory<BarangayFactory> */
    use HasFactory;

    protected $fillable = ['municipality_id', 'psgc_code', 'name', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => BarangayStatus::class];
    }

    /**
     * @return BelongsTo<Municipality, $this>
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * @return HasOne<BarangayProfile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(BarangayProfile::class);
    }

    /**
     * @return HasMany<Signatory, $this>
     */
    public function signatories(): HasMany
    {
        return $this->hasMany(Signatory::class);
    }

    public function isActive(): bool
    {
        return $this->status === BarangayStatus::Active;
    }
}
