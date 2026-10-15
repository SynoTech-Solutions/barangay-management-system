<?php

namespace App\Services\Barangays;

use App\Enums\AuditAction;
use App\Enums\ClearanceValidityMode;
use App\Enums\SignatoryOffice;
use App\Models\Barangay;
use App\Models\BarangayProfile;
use App\Models\Signatory;
use App\Services\Audit\AuditTrail;
use App\Support\StoragePaths;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class BarangayProfileService
{
    private const array PROFILE_ATTRIBUTES = [
        'telephone',
        'email',
        'id_card_ordinance_no',
        'clearance_validity_mode',
        'clearance_validity_months',
    ];

    public function __construct(private AuditTrail $audit) {}

    public function profile(Barangay $barangay): BarangayProfile
    {
        return BarangayProfile::query()->firstOrCreate(['barangay_id' => $barangay->id]);
    }

    /**
     * @return Collection<int, Signatory>
     */
    public function signatories(): Collection
    {
        return Signatory::query()->orderBy('office')->get();
    }

    /**
     * @param  array{telephone?: string|null, email?: string|null, id_card_ordinance_no?: string|null, clearance_validity_mode: ClearanceValidityMode, clearance_validity_months?: int|null}  $attributes
     */
    public function update(Barangay $barangay, array $attributes, int $actorId): BarangayProfile
    {
        if ($attributes['clearance_validity_mode'] === ClearanceValidityMode::EndOfYear) {
            $attributes['clearance_validity_months'] = null;
        }

        return DB::transaction(function () use ($barangay, $attributes, $actorId): BarangayProfile {
            $profile = $this->profile($barangay);
            $before = $this->snapshot($profile);
            $profile->fill($attributes)->save();

            $this->audit->record(AuditAction::Updated, $profile, $before, $this->snapshot($profile), $barangay->id, $actorId);

            return $profile;
        });
    }

    public function saveSignatory(
        Barangay $barangay,
        SignatoryOffice $office,
        string $name,
        ?string $title,
        ?UploadedFile $signature,
        bool $removeSignature,
        int $actorId,
    ): Signatory {
        return DB::transaction(function () use ($barangay, $office, $name, $title, $signature, $removeSignature, $actorId): Signatory {
            $signatory = Signatory::query()->firstOrNew(['office' => $office]);
            $before = $signatory->exists ? $signatory->only(['name', 'title', 'signature_path']) : null;

            $signatory->name = $name;
            $signatory->title = $title;

            if ($removeSignature && $signatory->signature_path !== null) {
                Storage::disk(StoragePaths::PRIVATE_DISK)->delete($signatory->signature_path);
                $signatory->signature_path = null;
            }

            if ($signature !== null) {
                $path = StoragePaths::signature($barangay->id, $office, $signature->extension());
                Storage::disk(StoragePaths::PRIVATE_DISK)->put($path, $signature->getContent());
                $signatory->signature_path = $path;
            }

            $signatory->save();

            $this->audit->record(
                $before === null ? AuditAction::Created : AuditAction::Updated,
                $signatory,
                $before,
                $signatory->only(['name', 'title', 'signature_path']),
                $barangay->id,
                $actorId,
            );

            return $signatory;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(BarangayProfile $profile): array
    {
        return [...$profile->only(self::PROFILE_ATTRIBUTES), 'clearance_validity_mode' => $profile->clearance_validity_mode->value];
    }
}
