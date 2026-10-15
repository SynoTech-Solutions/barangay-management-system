<?php

namespace App\Services\Residents;

use App\Enums\AuditAction;
use App\Models\Resident;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use App\Support\StoragePaths;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final readonly class ResidentPhotoService
{
    public function __construct(private AuditTrail $audit) {}

    public function replace(Resident $resident, UploadedFile $photo, User $actor): void
    {
        $path = StoragePaths::residentPhoto($resident->barangay_id, $resident->id, Str::lower((string) Str::ulid()), $photo->extension());
        $this->disk()->put($path, $photo->getContent());

        try {
            $previous = $this->store($resident, $path, $actor);
        } catch (Throwable $exception) {
            $this->disk()->delete($path);

            throw $exception;
        }

        if ($previous !== null) {
            $this->disk()->delete($previous);
        }
    }

    public function remove(Resident $resident, User $actor): void
    {
        $previous = $this->store($resident, null, $actor);

        if ($previous !== null) {
            $this->disk()->delete($previous);
        }
    }

    public function disk(): Filesystem
    {
        return Storage::disk(StoragePaths::PRIVATE_DISK);
    }

    private function store(Resident $resident, ?string $path, User $actor): ?string
    {
        return DB::transaction(function () use ($resident, $path, $actor): ?string {
            $previous = $resident->photo_path;

            $resident->photo_path = $path;
            $resident->photo_taken_at = $path === null ? null : now();
            $resident->updated_by = $actor->id;
            $resident->save();

            $this->audit->record(
                AuditAction::ResidentPhotoChanged,
                $resident,
                ['photo_path' => $previous],
                ['photo_path' => $path],
                $resident->barangay_id,
                $actor->id,
            );

            return $previous;
        });
    }
}
