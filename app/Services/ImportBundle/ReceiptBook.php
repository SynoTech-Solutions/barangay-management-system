<?php

namespace App\Services\ImportBundle;

use App\Domain\Money\OrNumber;
use App\Enums\ReceiptDocumentType;
use App\Models\OfficialReceipt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ReceiptBook implements ChunkAware
{
    /**
     * @var array<string, true>
     */
    private array $claimed = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $rows = [];

    public function __construct(
        private readonly int $barangayId,
        private readonly CarbonImmutable $now,
    ) {}

    public function claim(ReceiptDocumentType $type, int $documentId, ?string $orNumber, ?string $cancelledAt): bool
    {
        $normalized = OrNumber::normalize($orNumber);
        if ($normalized === null) {
            return true;
        }

        if ($cancelledAt === null) {
            if ($this->taken($normalized)) {
                return false;
            }
            $this->claimed[$normalized] = true;
        }

        $this->rows[] = [
            'barangay_id' => $this->barangayId,
            'or_number' => $normalized,
            'document_type' => $type->value,
            'document_id' => $documentId,
            'cancelled_at' => $cancelledAt,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ];

        return true;
    }

    public function flush(): void
    {
        TableWriter::insert((new OfficialReceipt)->getTable(), $this->rows);
        $this->rows = [];
    }

    public function begin(): void
    {
        $this->claimed = [];
        $this->rows = [];
    }

    public function commit(): void
    {
        $this->begin();
    }

    public function rollback(): void
    {
        $this->begin();
    }

    private function taken(string $normalized): bool
    {
        return isset($this->claimed[$normalized]) || DB::table((new OfficialReceipt)->getTable())
            ->where('barangay_id', $this->barangayId)
            ->where('or_number', $normalized)
            ->whereNull('cancelled_at')
            ->exists();
    }
}
