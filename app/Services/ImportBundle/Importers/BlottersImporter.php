<?php

namespace App\Services\ImportBundle\Importers;

use App\Enums\BlotterPartyRole;
use App\Enums\BlotterStatus;
use App\Enums\BundleEntity;
use App\Enums\DocumentKind;
use App\Enums\LookupType;
use App\Http\Requests\Blotter\BlotterPhotoRequest;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\TableWriter;
use App\Support\StoragePaths;

final class BlottersImporter extends BaseImporter
{
    public const string TABLE = 'blotters';

    private const int ATTACHMENT_CHUNK_SIZE = 100;

    public function entity(): BundleEntity
    {
        return BundleEntity::Blotters;
    }

    public function chunkSize(): int
    {
        return self::ATTACHMENT_CHUNK_SIZE;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        $ids = TableWriter::reserveIds(self::TABLE, count($records));
        $blotters = [];
        $parties = [];
        $photos = [];

        foreach ($records as $index => $record) {
            $year = $context->year($record->raw('reported_at'));
            if ($this->alreadyImported($context, $record)) {
                $this->observe($context, $record, 'source_number', DocumentKind::Blotter, $year);

                continue;
            }

            $id = $ids[$index];
            $reportedAt = $context->utcOrNow($record->raw('reported_at'));
            $status = BlotterStatus::tryFrom((string) $record->text('status')) ?? BlotterStatus::Open;
            [$number, $sourceNumber] = $this->number($context, $record, 'source_number', self::TABLE, 'blotter_number', DocumentKind::Blotter, $year);

            $blotters[] = [
                'id' => $id,
                'barangay_id' => $context->barangayId(),
                'blotter_number' => $number,
                'source_number' => $sourceNumber,
                'import_note' => $this->note($record),
                'incident_type_id' => $context->lookups->findOrCreate(LookupType::BlotterIncidentType, $record->requiredText('incident_type')),
                'incident_at' => $context->utc($record->raw('incident_at')) ?? $reportedAt,
                'reported_at' => $reportedAt,
                'place' => $record->text('place'),
                'narrative' => (string) $record->raw('narrative'),
                'status' => $status->value,
                'resolved_at' => $status === BlotterStatus::Resolved ? ($context->utc($record->raw('resolved_at')) ?? $reportedAt) : null,
                'resolution_notes' => $status === BlotterStatus::Resolved ? $record->raw('resolution_notes') : null,
                'cancelled_at' => $status === BlotterStatus::Cancelled ? ($context->utc($record->raw('cancelled_at')) ?? $reportedAt) : null,
                'cancel_reason' => $status === BlotterStatus::Cancelled ? $this->cancelReason($record) : null,
                'created_at' => $reportedAt,
                'updated_at' => $context->timestamp,
            ];

            foreach ($record->records('parties') as $position => $party) {
                $residentId = $this->optional($context, $party, 'resident_key', BundleEntity::Residents, $record->key());
                $name = $party->text('name');
                if ($residentId === null && $name === null) {
                    continue;
                }

                $parties[] = [
                    'barangay_id' => $context->barangayId(),
                    'blotter_id' => $id,
                    'role' => (BlotterPartyRole::tryFrom((string) $party->text('role')) ?? BlotterPartyRole::Complainant)->value,
                    'resident_id' => $residentId,
                    'name' => $residentId === null ? $name : null,
                    'address' => $residentId === null ? $party->text('address') : null,
                    'position' => $position,
                    'created_at' => $context->timestamp,
                    'updated_at' => $context->timestamp,
                ];
            }

            foreach ($record->strings('photos') as $relative) {
                $photo = $this->attachment(
                    $context,
                    $record,
                    $relative,
                    self::IMAGE_TYPES,
                    BlotterPhotoRequest::PHOTO_MAX_KILOBYTES,
                    fn (string $extension): string => StoragePaths::blotterPhoto($context->barangayId(), $id, $this->token(), $extension),
                );
                if ($photo !== null) {
                    $photos[] = [
                        'barangay_id' => $context->barangayId(),
                        'blotter_id' => $id,
                        'path' => $photo['path'],
                        'mime_type' => $photo['mime'],
                        'size_bytes' => $photo['size'],
                        'created_at' => $context->timestamp,
                        'updated_at' => $context->timestamp,
                    ];
                }
            }

            $this->remember($context, $record, self::TABLE, $id);
        }

        TableWriter::insert(self::TABLE, $blotters);
        TableWriter::insert('blotter_parties', $parties);
        TableWriter::insert('blotter_photos', $photos);
    }
}
