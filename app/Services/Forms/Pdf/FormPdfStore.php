<?php

namespace App\Services\Forms\Pdf;

use App\Domain\Forms\Pdf\AcroFormReader;
use App\Domain\Forms\Pdf\PdfFormField;
use App\Domain\Forms\Pdf\UnreadablePdf;
use App\Enums\ErrorCode;
use App\Exceptions\BmsException;
use App\Models\FormTemplateVersion;
use App\Support\StoragePaths;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final readonly class FormPdfStore
{
    private const string HASH_ALGORITHM = 'sha256';

    private const int FILE_NAME_MAX = 255;

    /**
     * @return list<PdfFormField>
     */
    public function inspect(string $contents): array
    {
        try {
            return AcroFormReader::read($contents);
        } catch (UnreadablePdf) {
            throw BmsException::of(ErrorCode::PdfFormUnreadable);
        }
    }

    public function store(int $barangayId, UploadedFile $file): StoredPdf
    {
        $contents = $file->getContent();
        $fields = $this->inspect($contents);
        $path = StoragePaths::formTemplatePdf($barangayId, Str::lower((string) Str::ulid()));
        $this->disk()->put($path, $contents);

        return new StoredPdf(
            $path,
            Str::limit(basename($file->getClientOriginalName()), self::FILE_NAME_MAX, ''),
            strlen($contents),
            hash(self::HASH_ALGORITHM, $contents),
            $fields,
            true,
        );
    }

    public function existing(FormTemplateVersion $version): ?StoredPdf
    {
        if ($version->pdf_path === null) {
            return null;
        }

        $contents = $this->contents($version);

        return new StoredPdf(
            $version->pdf_path,
            (string) $version->pdf_file_name,
            (int) $version->pdf_size_bytes,
            (string) $version->pdf_sha256,
            $this->inspect($contents),
            false,
        );
    }

    public function contents(FormTemplateVersion $version): string
    {
        $contents = $version->pdf_path === null ? null : $this->disk()->get($version->pdf_path);
        if ($contents === null) {
            throw BmsException::of(ErrorCode::PdfFormUnreadable);
        }

        return $contents;
    }

    public function discard(?StoredPdf $pdf): void
    {
        if ($pdf !== null && $pdf->isNew) {
            $this->disk()->delete($pdf->path);
        }
    }

    public function disk(): Filesystem
    {
        return Storage::disk(StoragePaths::PRIVATE_DISK);
    }
}
