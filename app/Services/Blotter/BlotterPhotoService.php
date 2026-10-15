<?php

namespace App\Services\Blotter;

use App\Enums\AuditAction;
use App\Models\Blotter;
use App\Models\BlotterPhoto;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use App\Support\StoragePaths;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final readonly class BlotterPhotoService
{
    private const string AUDIT_PATH_KEY = 'photo_path';

    public function __construct(
        private AuditTrail $audit,
        private BlotterService $blotters,
    ) {}

    public function add(Blotter $blotter, UploadedFile $file, User $actor): BlotterPhoto
    {
        $path = StoragePaths::blotterPhoto($blotter->barangay_id, $blotter->id, Str::lower((string) Str::ulid()), $file->extension());
        $this->disk()->put($path, $file->getContent());

        try {
            return DB::transaction(function () use ($blotter, $file, $path, $actor): BlotterPhoto {
                $blotter = $this->blotters->lockOpen($blotter);

                $photo = new BlotterPhoto(['path' => $path, 'mime_type' => (string) $file->getMimeType(), 'size_bytes' => (int) $file->getSize()]);
                $photo->blotter_id = $blotter->id;
                $photo->uploaded_by = $actor->id;
                $photo->save();

                $this->audit->record(AuditAction::BlotterPhotoAdded, $blotter, after: ['photo_id' => $photo->id, self::AUDIT_PATH_KEY => $path], barangayId: $blotter->barangay_id, actorId: $actor->id);

                return $photo;
            });
        } catch (Throwable $exception) {
            $this->disk()->delete($path);

            throw $exception;
        }
    }

    public function remove(Blotter $blotter, BlotterPhoto $photo, User $actor): void
    {
        DB::transaction(function () use ($blotter, $photo, $actor): void {
            $blotter = $this->blotters->lockOpen($blotter);
            $photo->deleted_by = $actor->id;
            $photo->deleted_at = CarbonImmutable::now();
            $photo->save();

            $this->audit->record(AuditAction::BlotterPhotoRemoved, $blotter, ['photo_id' => $photo->id, self::AUDIT_PATH_KEY => $photo->path], null, $blotter->barangay_id, $actor->id);
        });
    }

    public function disk(): Filesystem
    {
        return Storage::disk(StoragePaths::PRIVATE_DISK);
    }
}
