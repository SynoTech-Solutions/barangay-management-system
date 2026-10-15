# 0010. One official receipt registry enforces OR uniqueness

Date: 2026-10-21. Status: accepted.

## Context

An official receipt (OR) number may be used once per barangay among documents that are not cancelled (decisions D5 and D9). Each document table had its own partial unique index, so the database only stopped duplicates inside one table. Across tables, `AvailableOrNumber` checked all tables before saving and other collections took an advisory lock. Clearances and ID cards did not take that lock, so two requests saving the same number at the same moment could both pass the check and both commit.

## Decision

- Table `official_receipts` (tenant): `or_number` (normalized), `document_type` (`App\Enums\ReceiptDocumentType`), `document_id`, `cancelled_at`. One row per document (`unique (barangay_id, document_type, document_id)`).
- Partial unique index `official_receipts_active_or_number_unique` on `(barangay_id, or_number) where cancelled_at is null`. This index is the rule; nothing else decides.
- Normalization (`App\Domain\Money\OrNumber`): trim, collapse whitespace, uppercase. `or-55` and `OR-55` are the same receipt. Documents keep the number as typed.
- `App\Services\Receipts\OfficialReceiptRegistry` writes the receipt row in the caller's transaction: `sync()` after a document is created or edited, `void()` when it is cancelled. The insert runs in a savepoint; a unique violation on the index becomes `or_number_taken`, and the whole save rolls back.
- Callers: `DocumentLifecycle` (barangay clearances, business clearances, ID cards; cedulas and indigency certificates have no OR number) and `OtherCollectionService`. Finance part B adds liquidation refunds.
- `AvailableOrNumber` now reads the registry only. It still runs in Form Requests so the error appears on the field; it is a convenience, not the guarantee.
- The migration backfills every existing OR number in id order. When older data already holds a duplicate, the first active row wins and later duplicates get no receipt row; editing such a document then fails with `or_number_taken` until its number is corrected.
- The per-table partial unique indexes stay. They are cheap and still stop duplicates inside one table if a receipt row is ever missing.

## Consequences

- Duplicates are impossible across document types, including under concurrency.
- Every new document type that carries an OR number must add a `ReceiptDocumentType` case, map its model in `OfficialReceipt::typeFor()`, and call the registry in its service. Factories for those models use `RecordsOfficialReceipt` so test data matches production data.
