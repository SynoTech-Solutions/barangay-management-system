<?php

namespace App\Models;

use App\Enums\BlotterPartyRole;
use App\Enums\BlotterStatus;
use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\BlotterFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $barangay_id
 * @property string $blotter_number
 * @property int $incident_type_id
 * @property CarbonImmutable $incident_at
 * @property string|null $place
 * @property string $narrative
 * @property BlotterStatus $status
 * @property CarbonImmutable $reported_at
 * @property int|null $recorded_by
 * @property int|null $updated_by
 * @property CarbonImmutable|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $resolution_notes
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancel_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read LookupValue|null $incidentType
 * @property-read Collection<int, BlotterParty> $parties
 * @property-read Collection<int, BlotterPhoto> $photos
 * @property-read User|null $recorder
 * @property-read User|null $resolver
 * @property-read User|null $canceller
 */
class Blotter extends Model
{
    /** @use HasFactory<BlotterFactory> */
    use BelongsToBarangay, HasFactory;

    public const array DETAIL_ATTRIBUTES = ['incident_type_id', 'incident_at', 'place', 'narrative'];

    protected $fillable = self::DETAIL_ATTRIBUTES;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BlotterStatus::class,
            'incident_at' => 'datetime',
            'reported_at' => 'datetime',
            'resolved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === BlotterStatus::Open;
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function incidentType(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'incident_type_id');
    }

    /**
     * @return HasMany<BlotterParty, $this>
     */
    public function parties(): HasMany
    {
        return $this->hasMany(BlotterParty::class)->orderBy('role')->orderBy('position');
    }

    /**
     * @return HasMany<BlotterParty, $this>
     */
    public function respondents(): HasMany
    {
        return $this->hasMany(BlotterParty::class)->where('role', BlotterPartyRole::Respondent->value);
    }

    /**
     * @return HasMany<BlotterPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(BlotterPhoto::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
