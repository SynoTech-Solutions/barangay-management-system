<?php

namespace App\Services\Receipts;

use App\Domain\Money\OrNumber;
use App\Enums\ErrorCode;
use App\Enums\ReceiptDocumentType;
use App\Exceptions\BmsException;
use App\Models\OfficialReceipt;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class OfficialReceiptRegistry
{
    private const string OR_NUMBER = 'or_number';

    private const string CANCELLED_AT = 'cancelled_at';

    public function sync(Model $document): void
    {
        $type = OfficialReceipt::typeFor($document);
        if ($type === null) {
            return;
        }

        $value = $document->getAttribute(self::OR_NUMBER);
        $cancelledAt = $document->getAttribute(self::CANCELLED_AT);
        $this->record(
            $type,
            $this->barangayId($document),
            (int) $document->getKey(),
            OrNumber::normalize(is_string($value) ? $value : null),
            $cancelledAt instanceof CarbonImmutable ? $cancelledAt : null,
        );
    }

    public function void(Model $document): void
    {
        $type = OfficialReceipt::typeFor($document);
        if ($type === null) {
            return;
        }

        $this->receipt($type, $this->barangayId($document), (int) $document->getKey())
            ->whereNull(self::CANCELLED_AT)
            ->update([self::CANCELLED_AT => CarbonImmutable::now()]);
    }

    public function record(ReceiptDocumentType $type, int $barangayId, int $documentId, ?string $orNumber, ?CarbonImmutable $cancelledAt = null): void
    {
        $existing = $this->receipt($type, $barangayId, $documentId)->lockForUpdate()->first();

        if ($orNumber === null) {
            $existing?->delete();

            return;
        }

        if ($existing !== null && $existing->or_number === $orNumber && ($existing->cancelled_at === null) === ($cancelledAt === null)) {
            return;
        }

        try {
            DB::transaction(function () use ($existing, $type, $barangayId, $documentId, $orNumber, $cancelledAt): void {
                $receipt = $existing ?? new OfficialReceipt(['barangay_id' => $barangayId, 'document_type' => $type, 'document_id' => $documentId]);
                $receipt->or_number = $orNumber;
                $receipt->cancelled_at = $cancelledAt;
                $receipt->save();
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), OfficialReceipt::ACTIVE_INDEX)) {
                throw BmsException::of(ErrorCode::OrNumberTaken, ['number' => $orNumber]);
            }

            throw $exception;
        }
    }

    public static function isTaken(string $orNumber, ?Model $ignore = null): bool
    {
        $normalized = OrNumber::normalize($orNumber);
        $type = OfficialReceipt::typeFor($ignore);

        return $normalized !== null && OfficialReceipt::query()
            ->whereNull('cancelled_at')
            ->where('or_number', $normalized)
            ->when($type !== null, fn (Builder $query) => $query->whereNot(fn (Builder $inner) => $inner
                ->where('document_type', $type?->value)
                ->where('document_id', $ignore?->getKey())))
            ->exists();
    }

    /**
     * @return Builder<OfficialReceipt>
     */
    private function receipt(ReceiptDocumentType $type, int $barangayId, int $documentId): Builder
    {
        return OfficialReceipt::acrossBarangays()
            ->where('barangay_id', $barangayId)
            ->where('document_type', $type->value)
            ->where('document_id', $documentId);
    }

    private function barangayId(Model $document): int
    {
        return (int) $document->getAttribute('barangay_id');
    }
}
