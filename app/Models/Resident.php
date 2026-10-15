<?php

namespace App\Models;

use App\Domain\Residents\NameQuery;
use App\Enums\CivilStatus;
use App\Enums\Gender;
use App\Enums\LookupType;
use App\Models\Concerns\BelongsToBarangay;
use App\Support\Like;
use Carbon\CarbonImmutable;
use Database\Factories\ResidentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $barangay_id
 * @property string $resident_number
 * @property int|null $title_id
 * @property string $last_name
 * @property string $first_name
 * @property string|null $middle_name
 * @property string|null $name_extension
 * @property string $full_name
 * @property string|null $nickname
 * @property CarbonImmutable|null $birthdate
 * @property int|null $birthplace_municipality_id
 * @property Gender|null $gender
 * @property CivilStatus|null $civil_status
 * @property int|null $nationality_id
 * @property int|null $religion_id
 * @property int|null $blood_type_id
 * @property int|null $body_type_id
 * @property int|null $health_issue_id
 * @property int|null $profession_id
 * @property int|null $skill_id
 * @property int|null $income_bracket_id
 * @property string|null $height_cm
 * @property string|null $weight_kg
 * @property string|null $national_id
 * @property string|null $drivers_license
 * @property string|null $tin
 * @property string|null $sss_gsis
 * @property string|null $pagibig
 * @property string|null $philhealth
 * @property bool $is_registered_voter
 * @property string|null $voter_id
 * @property string|null $precinct_number
 * @property bool $is_living_with_parents
 * @property bool $is_senior_citizen_member
 * @property bool $is_pwd
 * @property bool $is_single_parent
 * @property bool $is_indigent
 * @property int|null $purok_id
 * @property string|null $street_address
 * @property bool $is_permanent_resident
 * @property int|null $residence_type_id
 * @property CarbonImmutable|null $residing_since
 * @property string|null $alternate_address
 * @property string|null $mobile_number
 * @property string|null $landline_number
 * @property string|null $email
 * @property string|null $facebook_url
 * @property bool $is_deceased
 * @property CarbonImmutable|null $deceased_on
 * @property string|null $cause_of_death
 * @property int|null $spouse_id
 * @property string|null $photo_path
 * @property CarbonImmutable|null $photo_taken_at
 * @property bool $is_incomplete
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Purok|null $purok
 * @property-read Resident|null $spouse
 * @property-read Municipality|null $birthplace
 * @property-read LookupValue|null $title
 * @property-read LookupValue|null $nationality
 * @property-read LookupValue|null $religion
 * @property-read LookupValue|null $bloodType
 * @property-read LookupValue|null $bodyType
 * @property-read LookupValue|null $healthIssue
 * @property-read LookupValue|null $profession
 * @property-read LookupValue|null $skill
 * @property-read LookupValue|null $incomeBracket
 * @property-read LookupValue|null $residenceType
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property-read Collection<int, LookupValue> $sectors
 * @property-read Collection<int, FamilyMember> $familyMembers
 * @property-read Collection<int, ResidentEducation> $educations
 * @property-read Collection<int, ResidentWorkHistory> $workHistories
 * @property-read ResidentPoliticalTag|null $politicalTag
 */
class Resident extends Model
{
    /** @use HasFactory<ResidentFactory> */
    use BelongsToBarangay, HasFactory, SoftDeletes;

    public const array PROFILE_ATTRIBUTES = [
        'title_id', 'last_name', 'first_name', 'middle_name', 'name_extension', 'nickname',
        'birthdate', 'birthplace_municipality_id', 'gender', 'civil_status',
        'nationality_id', 'religion_id', 'blood_type_id', 'body_type_id', 'health_issue_id',
        'profession_id', 'skill_id', 'income_bracket_id', 'height_cm', 'weight_kg',
        'national_id', 'drivers_license', 'tin', 'sss_gsis', 'pagibig', 'philhealth',
        'is_registered_voter', 'voter_id', 'precinct_number',
        'is_living_with_parents', 'is_senior_citizen_member', 'is_pwd', 'is_single_parent', 'is_indigent',
        'purok_id', 'street_address', 'is_permanent_resident', 'residence_type_id', 'residing_since', 'alternate_address',
        'mobile_number', 'landline_number', 'email', 'facebook_url',
        'is_deceased', 'deceased_on', 'cause_of_death',
    ];

    public const array LOOKUP_ATTRIBUTES = [
        'title_id' => LookupType::Title,
        'nationality_id' => LookupType::Nationality,
        'religion_id' => LookupType::Religion,
        'blood_type_id' => LookupType::BloodType,
        'body_type_id' => LookupType::BodyType,
        'health_issue_id' => LookupType::HealthIssue,
        'profession_id' => LookupType::Profession,
        'skill_id' => LookupType::Skill,
        'income_bracket_id' => LookupType::IncomeBracket,
        'residence_type_id' => LookupType::ResidenceType,
    ];

    protected $fillable = self::PROFILE_ATTRIBUTES;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'residing_since' => 'date',
            'deceased_on' => 'date',
            'photo_taken_at' => 'datetime',
            'gender' => Gender::class,
            'civil_status' => CivilStatus::class,
            'height_cm' => 'decimal:1',
            'weight_kg' => 'decimal:1',
            'is_registered_voter' => 'boolean',
            'is_living_with_parents' => 'boolean',
            'is_senior_citizen_member' => 'boolean',
            'is_pwd' => 'boolean',
            'is_single_parent' => 'boolean',
            'is_indigent' => 'boolean',
            'is_permanent_resident' => 'boolean',
            'is_deceased' => 'boolean',
            'is_incomplete' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Resident>  $query
     */
    public function scopeMatchingName(Builder $query, NameQuery $name): void
    {
        if ($name->isEmpty()) {
            return;
        }

        $query->where(function (Builder $inner) use ($name): void {
            $inner->where(function (Builder $names) use ($name): void {
                if ($name->isCommaForm()) {
                    $names->when($name->lastName !== null, fn (Builder $q) => $q->whereRaw('last_name ilike ?', [Like::contains((string) $name->lastName)]))
                        ->when($name->firstName !== null, fn (Builder $q) => $q->whereRaw('first_name ilike ?', [Like::contains((string) $name->firstName)]));

                    return;
                }

                foreach ($name->tokens as $token) {
                    $names->whereRaw('full_name ilike ?', [Like::contains($token)]);
                }
            })->orWhereRaw('resident_number ilike ?', [Like::startsWith($name->raw)]);
        });
    }

    /**
     * @param  Builder<Resident>  $query
     */
    public function scopeLiving(Builder $query): void
    {
        $query->where('is_deceased', false);
    }

    /**
     * @return BelongsTo<Purok, $this>
     */
    public function purok(): BelongsTo
    {
        return $this->belongsTo(Purok::class);
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function spouse(): BelongsTo
    {
        return $this->belongsTo(Resident::class, 'spouse_id');
    }

    /**
     * @return BelongsTo<Municipality, $this>
     */
    public function birthplace(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'birthplace_municipality_id');
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function title(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'title_id');
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function nationality(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'nationality_id');
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function religion(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'religion_id');
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function bloodType(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'blood_type_id');
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function bodyType(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'body_type_id');
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function healthIssue(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'health_issue_id');
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function profession(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'profession_id');
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'skill_id');
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function incomeBracket(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'income_bracket_id');
    }

    /**
     * @return BelongsTo<LookupValue, $this>
     */
    public function residenceType(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'residence_type_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return BelongsToMany<LookupValue, $this, ResidentSector>
     */
    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(LookupValue::class, 'resident_sector', 'resident_id', 'sector_id')
            ->using(ResidentSector::class)
            ->withPivot('barangay_id')
            ->withTimestamps();
    }

    /**
     * @return HasOne<ResidentPoliticalTag, $this>
     */
    public function politicalTag(): HasOne
    {
        return $this->hasOne(ResidentPoliticalTag::class);
    }

    /**
     * @return HasMany<FamilyMember, $this>
     */
    public function familyMembers(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /**
     * @return HasMany<ResidentEducation, $this>
     */
    public function educations(): HasMany
    {
        return $this->hasMany(ResidentEducation::class);
    }

    /**
     * @return HasMany<ResidentWorkHistory, $this>
     */
    public function workHistories(): HasMany
    {
        return $this->hasMany(ResidentWorkHistory::class);
    }
}
