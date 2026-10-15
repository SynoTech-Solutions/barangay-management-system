# Document numbering

`DocumentNumberAllocator::next(DocumentKind, year)` returns the next number for the current barangay.

Format: `PREFIX-YEAR-SEQUENCE` for yearly kinds, `PREFIX-SEQUENCE` for continuous kinds. The sequence is zero-padded to `BMS_DOCUMENT_NUMBER_WIDTH` and never truncated.

| Kind                         | Prefix     | Resets yearly |
| ---------------------------- | ---------- | ------------- |
| resident                     | RES        | no            |
| barangay-clearance / control | BC / BCC   | yes           |
| business                     | BUS        | no            |
| business-clearance / control | BUC / BUCC | yes           |
| blotter                      | BLT        | yes           |
| cedula                       | CTC        | yes           |
| indigency-certificate        | IND        | yes           |
| id-card                      | ID         | yes           |
| other-collection             | OC         | yes           |
| form-template                | FT         | no            |
| form-transaction             | FX         | yes           |
| disbursement-voucher         | DV         | yes           |
| liquidation-report           | LR         | yes           |
| check                        | CHK        | yes           |

## Concurrency

Allocation is one PostgreSQL statement: an upsert on `(barangay_id, kind, period)` that increments `last_value` and returns it. Two clerks issuing at the same moment get different numbers. Call it inside the transaction that saves the document so a rolled-back save also rolls back the number.

The year is passed in by the caller (from the issue date), so domain code never reads the clock.

A number is never computed from `MAX(number) + 1`: that read-then-write races between clerks and hands out duplicates. The upsert is atomic, so concurrent saves always get distinct numbers.

A data import keeps source numbers as display numbers where they are unique and then moves `document_sequences.last_value` to the highest imported number per kind and year, so new documents continue after them (`docs/import/bundle-format-v1.md`).
