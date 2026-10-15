<?php

namespace App\Services\ImportBundle\Importers;

use App\Domain\ImportBundle\UniqueName;
use App\Domain\Money\OrNumber;
use App\Enums\BundleEntity;
use App\Enums\DocumentKind;
use App\Enums\ImportProblem;
use App\Enums\IssuedDocumentStatus;
use App\Enums\LookupType;
use App\Enums\ReceiptDocumentType;
use App\Services\ImportBundle\EntityImporter;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\Record;
use App\Services\ImportBundle\TableWriter;
use App\Services\Numbering\DocumentNumberAllocator;
use Closure;
use finfo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class BaseImporter implements EntityImporter
{
    public const int NUMBER_MAX = 32;

    public const int TEXT_MAX = 255;

    public const int CHUNK_SIZE = 500;

    public const array IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public const array PDF_TYPES = ['application/pdf' => 'pdf'];

    private const int BYTES_PER_KILOBYTE = 1024;

    private const string NOTE_SEPARATOR = ' ';

    public function __construct(protected readonly DocumentNumberAllocator $numbers) {}

    public function chunkSize(): int
    {
        return self::CHUNK_SIZE;
    }

    public function finish(ImportContext $context): void {}

    protected function alreadyImported(ImportContext $context, Record $record): bool
    {
        if (! $context->ids->has($context->entity->value, $record->key())) {
            return false;
        }

        $context->skipped($record->key(), ImportProblem::AlreadyImported);

        return true;
    }

    protected function remember(ImportContext $context, Record $record, string $table, int $id): void
    {
        $context->ids->remember($context->entity->value, $record->key(), $table, $id);
        $context->imported();
    }

    protected function required(ImportContext $context, Record $record, string $field, BundleEntity|string $target, ?string $reportKey = null): ?int
    {
        $id = $context->ids->find($target instanceof BundleEntity ? $target->value : $target, $record->ref($field));
        if ($id === null) {
            $context->failed($reportKey ?? $record->key(), ImportProblem::DanglingReference);
        }

        return $id;
    }

    protected function optional(ImportContext $context, Record $record, string $field, BundleEntity|string $target, ?string $reportKey = null): ?int
    {
        $key = $record->ref($field);
        if ($key === null) {
            return null;
        }

        $id = $context->ids->find($target instanceof BundleEntity ? $target->value : $target, $key);
        if ($id === null) {
            $context->warn($reportKey ?? $record->key(), ImportProblem::DanglingReference);
        }

        return $id;
    }

    protected function lookup(ImportContext $context, LookupType $type, ?string $label): ?int
    {
        return $label === null ? null : $context->lookups->findOrCreate($type, $label);
    }

    /**
     * @param  literal-string  $column
     * @return array{0: string, 1: string|null}
     */
    protected function number(ImportContext $context, Record $record, string $field, string $table, string $column, DocumentKind $kind, int $year): array
    {
        $candidate = $record->text($field);
        if ($candidate !== null && mb_strlen($candidate) <= self::NUMBER_MAX && $context->unique->claim($table, $column, $candidate)) {
            $context->sequences->observe($kind, $candidate, $year);

            return [$candidate, null];
        }

        $allocated = $this->numbers->next($kind, $year);
        $context->unique->claim($table, $column, $allocated);
        if ($candidate !== null) {
            $context->warn($record->key(), ImportProblem::NumberReplaced);
        }

        return [$allocated, $candidate];
    }

    protected function observe(ImportContext $context, Record $record, string $field, DocumentKind $kind, ?int $year): void
    {
        $context->sequences->observe($kind, $record->text($field), $year);
    }

    /**
     * @param  literal-string  $column
     */
    protected function uniqueName(ImportContext $context, Record $record, string $table, string $column, string $base, int $max): string
    {
        for ($attempt = 1; ; $attempt++) {
            $candidate = UniqueName::candidate($base, $attempt, $max);
            if ($context->unique->claim($table, $column, $candidate, true)) {
                if ($attempt > 1) {
                    $context->warn($record->key(), ImportProblem::NameTaken);
                }

                return $candidate;
            }
        }
    }

    /**
     * @return array{status: string, issued_at: string, released_at: string|null, cancelled_at: string|null, cancel_reason: string|null, created_at: string, updated_at: string}
     */
    protected function lifecycle(ImportContext $context, Record $record): array
    {
        $status = IssuedDocumentStatus::tryFrom((string) $record->text('status')) ?? IssuedDocumentStatus::Issued;
        $issuedAt = $context->utcOrNow($record->raw('issued_at'));

        return [
            'status' => $status->value,
            'issued_at' => $issuedAt,
            'released_at' => $status === IssuedDocumentStatus::Released ? ($context->utc($record->raw('released_at')) ?? $issuedAt) : null,
            'cancelled_at' => $status === IssuedDocumentStatus::Cancelled ? ($context->utc($record->raw('cancelled_at')) ?? $issuedAt) : null,
            'cancel_reason' => $status === IssuedDocumentStatus::Cancelled ? $this->cancelReason($record) : null,
            'created_at' => $issuedAt,
            'updated_at' => $context->timestamp,
        ];
    }

    protected function cancelReason(Record $record): string
    {
        return $record->text('cancel_reason') ?? (string) __('import.cancel_reason');
    }

    /**
     * @return array{verified_by_name: string|null, certified_by_name: string|null}
     */
    protected function signatories(Record $record): array
    {
        return [
            'verified_by_name' => $record->text('verified_by_name'),
            'certified_by_name' => $record->text('certified_by_name'),
        ];
    }

    /**
     * @return array{or_number: string|null, note: string|null}
     */
    protected function receipt(ImportContext $context, Record $record, ReceiptDocumentType $type, int $documentId, ?string $cancelledAt): array
    {
        $orNumber = OrNumber::normalize($record->text('or_number'));
        if (! $context->receipts->claim($type, $documentId, $orNumber, $cancelledAt)) {
            $context->warn($record->key(), ImportProblem::OrNumberConflict);

            return ['or_number' => null, 'note' => (string) __('import.or_conflict', ['number' => $orNumber])];
        }

        return ['or_number' => $orNumber, 'note' => null];
    }

    protected function note(Record $record, ?string ...$extra): ?string
    {
        $parts = array_values(array_filter([$record->text('import_note'), ...$extra], fn (?string $part): bool => $part !== null && $part !== ''));

        return $parts === [] ? null : mb_substr(implode(self::NOTE_SEPARATOR, $parts), 0, self::TEXT_MAX);
    }

    /**
     * @param  array<string, string>  $types
     * @param  Closure(string): string  $target
     * @return array{path: string, mime: string, size: int, sha256: string}|null
     */
    protected function attachment(ImportContext $context, Record $record, ?string $relative, array $types, int $maxKilobytes, Closure $target, bool $required = false): ?array
    {
        $loaded = $this->loadAttachment($context, $record, $relative, $types, $maxKilobytes, $required);

        return $loaded === null ? null : $this->storeAttachment($context, $loaded, $target);
    }

    /**
     * @param  array<string, string>  $types
     * @return array{bytes: string, mime: string, extension: string}|null
     */
    protected function loadAttachment(ImportContext $context, Record $record, ?string $relative, array $types, int $maxKilobytes, bool $required = false): ?array
    {
        if ($relative === null) {
            return null;
        }

        $report = fn (ImportProblem $problem) => $required ? $context->failed($record->key(), $problem) : $context->warn($record->key(), $problem);
        $file = $context->bundle->attachment($relative);
        if ($file === null) {
            $report(ImportProblem::AttachmentMissing);

            return null;
        }

        $size = filesize($file);
        $bytes = $size === false || $size > $maxKilobytes * self::BYTES_PER_KILOBYTE ? false : file_get_contents($file);
        $mime = is_string($bytes) && $bytes !== '' ? (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) : false;
        $extension = is_string($mime) ? ($types[$mime] ?? null) : null;
        if (! is_string($bytes) || ! is_string($mime) || $extension === null) {
            $report(ImportProblem::AttachmentUnsupported);

            return null;
        }

        return ['bytes' => $bytes, 'mime' => $mime, 'extension' => $extension];
    }

    /**
     * @param  array{bytes: string, mime: string, extension: string}  $loaded
     * @param  Closure(string): string  $target
     * @return array{path: string, mime: string, size: int, sha256: string}
     */
    protected function storeAttachment(ImportContext $context, array $loaded, Closure $target): array
    {
        $path = $target($loaded['extension']);
        $context->files->put($path, $loaded['bytes']);

        return ['path' => $path, 'mime' => $loaded['mime'], 'size' => strlen($loaded['bytes']), 'sha256' => hash('sha256', $loaded['bytes'])];
    }

    /**
     * @param  literal-string  $column
     * @param  array<string, mixed>  $row
     */
    protected function findOrInsert(ImportContext $context, string $table, string $column, string $value, array $row, bool $caseInsensitive = true): int
    {
        $query = DB::table($table)->where('barangay_id', $context->barangayId());
        $caseInsensitive ? $query->whereRaw("lower({$column}) = lower(?)", [$value]) : $query->where($column, $value);
        $existing = $query->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        $id = TableWriter::reserveId($table);
        TableWriter::insert($table, [[
            ...$row,
            'id' => $id,
            'barangay_id' => $context->barangayId(),
            $column => $value,
            'created_at' => $context->timestamp,
            'updated_at' => $context->timestamp,
        ]]);

        return $id;
    }

    protected function token(): string
    {
        return Str::lower((string) Str::ulid());
    }
}
