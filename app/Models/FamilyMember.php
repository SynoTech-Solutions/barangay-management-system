<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBarangay;
use Carbon\CarbonImmutable;
use Database\Factories\FamilyMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $barangay_id
 * @property int $resident_id
 * @property int $relationship_id
 * @property string $full_name
 * @property CarbonImmutable|null $birthdate
 * @property int|null $related_resident_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property-read Resident $resident
 * @property-read LookupValue $relationship
 * @property-read Resident|null $relatedResident
 */
class FamilyMember extends Model
{
    /** @use HasFactory<FamilyMemberFactory> */
    use BelongsToBarangay, HasFactory, SoftDeletes;

    public const array AUDITED_ATTRIBUTES = ['relationship_id', 'full_name', 'birthdate', 'related_resident_id'];

    protected $fillable = ['relationship_id', 'full_name', 'birthdate', 'related_resident_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['birthdate' => 'date'];
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function relationship(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'relationship_id');
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function relatedResident(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'related_resident_id');
    }
}
