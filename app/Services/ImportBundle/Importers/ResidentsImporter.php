<?php

namespace App\Services\ImportBundle\Importers;

use App\Domain\Residents\NameNormalizer;
use App\Domain\Residents\PhilippineMobileNumber;
use App\Enums\BundleEntity;
use App\Enums\DocumentKind;
use App\Enums\ImportProblem;
use App\Enums\LookupType;
use App\Http\Requests\Residents\ResidentPhotoRequest;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\Record;
use App\Services\ImportBundle\TableWriter;
use App\Support\StoragePaths;
use Illuminate\Support\Facades\DB;

final class ResidentsImporter extends BaseImporter
{
    public const string TABLE = 'residents';

    private const int PHOTO_CHUNK_SIZE = 100;

    private const int MEASUREMENT_DECIMALS = 1;

    private const array LOOKUPS = [
        'title' => ['title_id', LookupType::Title],
        'nationality' => ['nationality_id', LookupType::Nationality],
        'religion' => ['religion_id', LookupType::Religion],
        'blood_type' => ['blood_type_id', LookupType::BloodType],
        'body_type' => ['body_type_id', LookupType::BodyType],
        'health_issue' => ['health_issue_id', LookupType::HealthIssue],
        'profession' => ['profession_id', LookupType::Profession],
        'skill' => ['skill_id', LookupType::Skill],
        'income_bracket' => ['income_bracket_id', LookupType::IncomeBracket],
        'residence_type' => ['residence_type_id', LookupType::ResidenceType],
    ];

    private const array TEXTS = [
        'nickname', 'national_id', 'drivers_license', 'tin', 'sss_gsis', 'pagibig', 'philhealth',
        'street_address', 'landline_number', 'email', 'facebook_url',
    ];

    private const array FLAGS = [
        'is_living_with_parents', 'is_senior_citizen_member', 'is_pwd', 'is_single_parent', 'is_indigent', 'is_permanent_resident',
    ];

    /**
     * @var array<string, string>
     */
    private array $spouses = [];

    /**
     * @var array<string, int|null>
     */
    private array $municipalities = [];

    public function entity(): BundleEntity
    {
        return BundleEntity::Residents;
    }

    public function chunkSize(): int
    {
        return self::PHOTO_CHUNK_SIZE;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        $ids = TableWriter::reserveIds(self::TABLE, count($records));
        $residents = [];
        $sectors = [];
        $tags = [];

        foreach ($records as $index => $record) {
            $key = $record->key();
            $spouse = $record->ref('spouse_key');
            if ($spouse !== null && $spouse !== $key) {
                $this->spouses[$key] = $spouse;
            }

            if ($this->alreadyImported($context, $record)) {
                $this->observe($context, $record, 'source_number', DocumentKind::Resident, null);

                continue;
            }

            $last = $record->text('last_name');
            $first = $record->text('first_name');
            if ($last === null && $first === null) {
                $context->failed($key, ImportProblem::MissingName);

                continue;
            }

            $id = $ids[$index];
            $createdAt = $context->utcOrNow($record->raw('created_at'));
            $updatedAt = $context->utc($record->raw('updated_at')) ?? $createdAt;
            [$number, $sourceNumber] = $this->number($context, $record, 'source_number', self::TABLE, 'resident_number', DocumentKind::Resident, $context->year($record->raw('created_at')));
            $voter = $record->bool('is_registered_voter');
            $deceased = $record->bool('is_deceased');
            $permanent = $record->bool('is_permanent_resident');
            $middle = $record->text('middle_name');
            $extension = $record->text('name_extension');

            $row = [
                'id' => $id,
                'barangay_id' => $context->barangayId(),
                'resident_number' => $number,
                'source_number' => $sourceNumber,
                'import_note' => $this->note($record),
                'last_name' => $last ?? '',
                'first_name' => $first ?? '',
                'middle_name' => $middle,
                'name_extension' => $extension,
                'full_name' => NameNormalizer::fullName($last ?? '', $first ?? '', $middle, $extension),
                'birthdate' => $this->birthdate($context, $record),
                'birthplace_municipality_id' => $this->birthplace($context, $record),
                'gender' => $record->text('gender'),
                'civil_status' => $record->text('civil_status'),
                'height_cm' => $this->measurement($record, 'height_cm'),
                'weight_kg' => $this->measurement($record, 'weight_kg'),
                'is_registered_voter' => $voter,
                'voter_id' => $voter ? $record->text('voter_id') : null,
                'precinct_number' => $voter ? $record->text('precinct_number') : null,
                'purok_id' => $this->optional($context, $record, 'purok_key', BundleEntity::Puroks),
                'residing_since' => $record->raw('residing_since'),
                'alternate_address' => $permanent ? null : $record->raw('alternate_address'),
                'mobile_number' => PhilippineMobileNumber::normalize($record->raw('mobile_number')),
                'is_deceased' => $deceased,
                'deceased_on' => $deceased ? $record->raw('deceased_on') : null,
                'cause_of_death' => $deceased ? $record->text('cause_of_death') : null,
                'is_incomplete' => $record->bool('is_incomplete') || $last === null || $first === null,
                'photo_path' => null,
                'photo_taken_at' => null,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'deleted_at' => $context->utc($record->raw('deleted_at')),
            ];

            foreach (self::TEXTS as $field) {
                $row[$field] = $record->text($field);
            }

            foreach (self::FLAGS as $field) {
                $row[$field] = $record->bool($field);
            }

            foreach (self::LOOKUPS as $field => [$column, $type]) {
                $row[$column] = $this->lookup($context, $type, $record->text($field));
            }

            foreach ($record->strings('sectors') as $label) {
                $sectorId = $context->lookups->findOrCreate(LookupType::Sector, $label);
                $sectors[$id.'|'.$sectorId] = [
                    'barangay_id' => $context->barangayId(),
                    'resident_id' => $id,
                    'sector_id' => $sectorId,
                    'created_at' => $context->timestamp,
                    'updated_at' => $context->timestamp,
                ];
            }

            $tag = $this->tag($context, $record, $id);
            if ($tag !== null) {
                $tags[] = $tag;
            }

            $photo = $this->attachment(
                $context,
                $record,
                $record->raw('photo'),
                self::IMAGE_TYPES,
                ResidentPhotoRequest::PHOTO_MAX_KILOBYTES,
                fn (string $extension): string => StoragePaths::residentPhoto($context->barangayId(), $id, $this->token(), $extension),
            );
            if ($photo !== null) {
                $row['photo_path'] = $photo['path'];
                $row['photo_taken_at'] = $context->utcOrNow($record->raw('photo_taken_at'));
            }

            $residents[] = $row;
            $this->remember($context, $record, self::TABLE, $id);
        }

        TableWriter::insert(self::TABLE, $residents);
        TableWriter::insert('resident_sector', array_values($sectors));
        TableWriter::insert('resident_political_tags', $tags);
    }

    public function finish(ImportContext $context): void
    {
        foreach ($this->spouses as $key => $spouseKey) {
            $residentId = $context->ids->find(BundleEntity::Residents->value, $key);
            $spouseId = $context->ids->find(BundleEntity::Residents->value, $spouseKey);
            if ($residentId === null) {
                continue;
            }

            if ($spouseId === null) {
                $context->warn($key, ImportProblem::DanglingReference);

                continue;
            }

            foreach ([[$residentId, $spouseId], [$spouseId, $residentId]] as [$id, $partner]) {
                DB::table(self::TABLE)
                    ->where('barangay_id', $context->barangayId())
                    ->where('id', $id)
                    ->whereNull('spouse_id')
                    ->update(['spouse_id' => $partner]);
            }
        }
    }

    private function birthdate(ImportContext $context, Record $record): ?string
    {
        $birthdate = $record->raw('birthdate');
        if ($birthdate !== null && $birthdate > $context->today()) {
            $context->warn($record->key(), ImportProblem::FutureBirthdate);

            return null;
        }

        return $birthdate;
    }

    private function birthplace(ImportContext $context, Record $record): ?int
    {
        $code = $record->raw('birthplace_psgc');
        if ($code === null) {
            return null;
        }

        if (! array_key_exists($code, $this->municipalities)) {
            $id = DB::table('municipalities')->where('psgc_code', $code)->value('id');
            $this->municipalities[$code] = $id === null ? null : (int) $id;
        }

        if ($this->municipalities[$code] === null) {
            $context->warn($record->key(), ImportProblem::UnknownBirthplace);
        }

        return $this->municipalities[$code];
    }

    private function measurement(Record $record, string $field): ?string
    {
        $value = $record->number($field);

        return $value === null ? null : number_format($value, self::MEASUREMENT_DECIMALS, '.', '');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tag(ImportContext $context, Record $record, int $residentId): ?array
    {
        if ($record->ref('color_category_key') === null) {
            return null;
        }

        if (! $context->colorTagsEnabled) {
            $context->warn($record->key(), ImportProblem::ColorTagSkipped);

            return null;
        }

        $categoryId = $this->optional($context, $record, 'color_category_key', BundleEntity::ColorCategories);

        return $categoryId === null ? null : [
            'barangay_id' => $context->barangayId(),
            'resident_id' => $residentId,
            'color_category_id' => $categoryId,
            'tagged_by' => null,
            'created_at' => $context->timestamp,
            'updated_at' => $context->timestamp,
        ];
    }
}
