<?php

namespace App\Models;

use App\Enums\BlotterPartyRole;
use App\Models\Concerns\BelongsToBarangay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $blotter_id
 * @property BlotterPartyRole $role
 * @property int|null $resident_id
 * @property string|null $name
 * @property string|null $address
 * @property int $position
 * @property-read Resident|null $resident
 * @property-read Blotter|null $blotter
 */
class BlotterParty extends Model
{
    use BelongsToBarangay;

    protected $fillable = ['role', 'resident_id', 'name', 'address', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['role' => BlotterPartyRole::class];
    }

    public function displayName(): string
    {
        return $this->resident->full_name ?? $this->name ?? '';
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Blotter, $this>
     */
    public function blotter(): BelongsTo
    {
        return $this->belongsTo(Blotter::class);
    }
}
