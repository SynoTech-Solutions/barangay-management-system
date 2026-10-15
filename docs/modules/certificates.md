# Certificates

Part A: barangay clearances, the business registry, business clearances, and dashboard counters. Part B: cedula (community tax certificate), blotter, and indigency certificates. See the Part B section below.

## What it does

| Area                        | Behavior                                                                                                                                                                                    |
| --------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Issue barangay clearance    | Resident chosen with the shared picker; type and purpose from lookups; OR number, amount, documentary stamp, cedula reference. Clearance and control numbers assigned on save.              |
| Edit                        | Only while for releasing. Released and cancelled clearances answer `document_not_editable`.                                                                                                 |
| Print and reprint           | A4 or Letter page; every page load counts a print and writes an audit row; prints after the first show "Reprint". Cancelled clearances cannot be printed.                                   |
| Records list                | Filters: name or number, status, type, purpose, issue date range. Footer totals of amount, documentary stamp, and both, excluding cancelled clearances.                                     |
| Release and cancel          | Bulk over selected rows, all or nothing under row locks. Cancel needs a reason.                                                                                                             |
| Resident page               | "Clearances" tab lists the resident's clearances with edit, print, and cancel. "Issue clearance" button pre-fills the resident. There is no delete; removing a clearance cancels it.        |
| Business registry           | Register and edit businesses; owner is a resident from the picker or a free-text name; mark active or inactive. Filters by name, number, owner, status, category, nature, ownership, purok. |
| Business report             | Counts by category, nature of business, type of ownership, purok, or status, optionally limited to active or inactive businesses (`/reports/businesses`).                                   |
| Expired business clearances | Active businesses that have had a clearance and hold no unexpired one. "Renew" opens a new business clearance with the business filled in.                                                  |
| Business clearance          | Same mechanics as barangay clearances: two numbers, stored expiry, OR number, release, cancel, print.                                                                                       |
| Lookups                     | Clearance type, clearance purpose, business category, nature, and ownership type are lookup types in Administration → Lookup values. No new screen.                                         |
| Dashboard counters          | Cards for documents for release, issued today, and expired business clearances.                                                                                                             |

Not built: custom print layouts and template placeholders for clearances (`custom-print-templates` stays off), fingerprints on clearances (`clearance-fingerprints` stays off with `biometric-capture`), and saved grid column layouts (`grid-layout-persistence`).

## Data placement

All tables are tenant tables: `barangay_id` is not null, indexes start with it, and models use `BelongsToBarangay`.

| Table                 | Contents                                                                                                                                                                                                                                                                       |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `barangay_clearances` | Resident, clearance and control numbers, status, type and purpose lookups, OR number, amount, documentary stamp, cedula number, place, and date, `expires_on`, signatory snapshots, issue, release, and cancel actor and time, cancel reason, `print_count`, `last_printed_at` |
| `businesses`          | Business number, name, owner resident or owner name, category, nature, and ownership lookups, purok, street address, plate number, operating since, capitalization, gross receipts, status, registered and updated by                                                          |
| `business_clearances` | Business, clearance and control numbers, snapshots of business name, address, owner resident and name, then the same payment, expiry, signatory, lifecycle, and print columns as barangay clearances                                                                           |

Unique keys: clearance and control numbers per barangay; business number per barangay; business name per barangay ignoring case (`lower(name)` index); OR number per barangay and table among rows that are not cancelled (partial index). Amounts are `numeric(12,2)` with non-negative checks (ADR 0007).

## Shared document lifecycle

ID cards, barangay clearances, and business clearances extend `App\Models\IssuedDocument` and share:

- `IssuedDocumentStatus`: `issued` (shown as "For releasing"), `released`, `cancelled`. Labels in `lang/en/documents.php`.
- `App\Services\Documents\DocumentLifecycle`: issue (stamps issuer and time, releases at once when `document-release-step` is off), update (row lock, pending only, changed-fields audit), bulk release and cancel (all or nothing), and print recording. Each model maps lifecycle events to its own audit actions.
- `App\Services\Documents\ResidentIssuanceGuard`: deceased residents (`resident_deceased`), missing photo while `issuance-requires-photo` is on (`resident_photo_required`), and, for barangay clearances only, the complaint hold (`resident_on_hold`).
- Requests: `DocumentBatchRequest`, `RequiresCancelReason`, `ValidatesDocumentPayment`, `DocumentListRequest`.
- Vue: `DocumentBulkActions`, `DocumentStatusBadge`, `PaymentFields`, `PrintSheet`, `PaymentSummary`, `SignatureBlock`.
- Print data: `App\Queries\DocumentPrintContext` (header, resident facts and address, QR code, captain signature, payment block). The ID card print uses it too.

## Flags

| Flag                             | Effect                                                                                                                                                                            |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `barangay-clearance`             | Every `/clearances` route answers 404; sidebar, resident tab, and "Issue clearance" hidden                                                                                        |
| `business-registry`              | Every `/businesses` route and `/reports/businesses` answer 404. `business-clearance` requires it                                                                                  |
| `business-clearance`             | Every `/business-clearances` route answers 404                                                                                                                                    |
| `document-release-step`          | Off: issuing sets `released_at = issued_at` and `released_by` = issuer, release routes answer 404, release buttons and "for release" counters are hidden. Applies to ID cards too |
| `clearance-doc-stamp`            | Off: the field is hidden, ignored on save, and left out of print totals. Applies to both clearances                                                                               |
| `clearance-ctc-reference`        | Off: cedula fields are hidden, ignored on save, and not printed. Applies to both clearances                                                                                       |
| `clearance-qr-code`              | Off: no QR code on the printout. The QR code carries only the clearance number (decision D7)                                                                                      |
| `captain-signature-on-clearance` | On: the punong barangay's stored signature image is printed, but only while the current punong barangay's name equals the name captured on the document                           |
| `secretary-approval-block`       | Off: the secretary is not captured as "verified by" at issue and the block is not printed. Barangay clearances only                                                               |
| `issuance-requires-photo`        | On: barangay clearances (like ID cards) need a resident photo                                                                                                                     |
| `unresolved-complaint-hold`      | On: respondents in open blotters cannot get a barangay clearance or ID card (Part B)                                                                                              |
| `dashboard-pending-counters`     | Off: no counters on the dashboard                                                                                                                                                 |

The four clearance options above no longer require `barangay-clearance`, because they also shape business clearances.

## Permissions

| Permission                                 | Allows                                                     | Built-in roles                   |
| ------------------------------------------ | ---------------------------------------------------------- | -------------------------------- |
| `clearances.view`                          | Both clearance lists, expired list, resident tab, counters | every barangay role              |
| `clearances.issue`                         | Issue, edit pending, print                                 | barangay-admin, secretary, clerk |
| `clearances.release` / `clearances.cancel` | Bulk release / bulk cancel                                 | barangay-admin, secretary        |
| `businesses.view`                          | Registry list, business page, business picker              | every barangay role              |
| `businesses.manage`                        | Register, edit, activate, deactivate                       | barangay-admin, secretary        |
| `reports.view`                             | Business report                                            | every barangay role              |

## Decisions

- **Numbers.** `DocumentNumberAllocator` inside the issue transaction: `BC-2026-000001` and `BCC-2026-000001`, `BUC-…` and `BUCC-…`, `BUS-000001`. Both numbers of a clearance come from the same transaction, so a rejected issue rolls both back. Yearly kinds use the server's issue year. Opening the form never takes a number.
- **Expiry (decision D4).** Computed once at issue by `App\Domain\Certificates\ClearanceExpiry` from the barangay profile: end of the issue year (default) or issue date plus N months, clamped to the month's last day. Stored in `expires_on`; printouts and lists show the stored date. Editing never moves it. A clearance is valid through its expiry date.
- **Signatories.** Punong barangay ("certified by") and, when `secretary-approval-block` is on, secretary ("verified by") names and titles are copied at issue. Edits keep the issue-time snapshot.
- **OR numbers (decision D5).** Optional. Unique per barangay, ignoring case and spacing, across every document and collection that is not cancelled. The `official_receipts` registry and its partial unique index enforce it (ADR 0010); `App\Rules\AvailableOrNumber` reports it on the field.
- **Reprints.** Allowed for issued and released documents; the first print is unmarked and later prints carry a "Reprint" marker. Each print is audited.
- **Print wording.** "Issued this … day" uses the issue date, not the print date, so a reprint matches the first print. Printed text is gender-neutral instead of choosing pronouns from the resident's gender.
- **Complaint hold.** `App\Services\Holds\ResidentHoldChecker` is bound to `BlotterHoldChecker` (Part B). The hold is checked at save time.
- **Business owner.** Exactly one of a registered resident or a free-text name. The clearance snapshots the owner name and business name and address, so later edits do not change issued clearances.
- **Business required fields.** Name (unique per barangay, case and spacing ignored), category, nature, and type of ownership. Inactive businesses cannot get clearances (`business_inactive`).
- **Expired list.** Cancelled clearances do not count as valid. A business that has never had a clearance that was not cancelled is not listed.
- **Dashboard counters.** `App\Queries\DashboardCounters` builds one SQL statement with one scalar subquery per visible counter. A counter is visible when `dashboard-pending-counters`, the document's module flag, and the matching view permission hold; "for release" counters also need `document-release-step`. Counters with zero are shown.
- **Paper.** The print page switches between Letter (default) and A4 in the browser; switching does not count another print.
- **Finance link.** OR number and amounts are stored on each document so the finance module can join collections later. No collections screen reads them yet.

## Part B

| Area                  | Behavior                                                                                                                                                                                                                                     |
| --------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Issue cedula          | Individual taxpayer from the resident picker; optional pre-printed form number; income figures; exempt checkbox. The tax is computed by `CommunityTaxCalculator` (ADR 0008) and shown before saving                                          |
| View and print        | Read-only page with the stored computation. Print is a plain summary page (Letter or A4) with print count and reprint marker; it is not laid out for the pre-printed form (decision D15)                                                     |
| Records               | List with name or number, status, and date filters; total of amounts excluding cancelled; bulk release and cancel through the shared lifecycle                                                                                               |
| Presets               | Barangay presets (name, exempt flag, income figures) that fill the form; hide and restore instead of delete                                                                                                                                  |
| CTC reference         | ID card and barangay clearance forms offer the resident's issued cedulas; choosing one fills CTC number (form number, else cedula number), place, and date. Typing stays possible                                                            |
| Record blotter        | Incident type (lookup), date and time, place, narrative, one or more complainants and any number of respondents; each party is a resident from the picker or a typed name and address. Quick add from the picker behind `resident-quick-add` |
| Edit, resolve, cancel | Edit only while open. Resolve with optional notes (`blotter.resolve`). Cancel with a required reason. Resolved and cancelled entries answer `blotter_not_open`                                                                               |
| Incident photos       | Several JPEG, PNG, or WebP photos up to `BlotterPhotoRequest::PHOTO_MAX_KILOBYTES`; private disk; streamed by an authorized route with `Cache-Control: private, no-store`; removal is a soft delete that keeps the file                      |
| Resident page         | "Blotter" tab listing entries where the resident is complainant or respondent, with role and status. Never shows the narrative                                                                                                               |
| Complaint hold        | Respondents in open blotters cannot get a barangay clearance or an ID card; the picker marks them when asked                                                                                                                                 |
| Indigency certificate | Resident, purpose (lookup `certificate-purpose`), number, punong barangay snapshot, built-in print layout, lifecycle reuse. Off by default (decision D17)                                                                                    |
| Dashboard             | Cedulas for release, cedulas issued today, open blotter cases                                                                                                                                                                                |

### Part B data

| Table                    | Contents                                                                                                                                                                                                                      |
| ------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `cedulas`                | Resident, `cedula_number`, optional `form_number` (unique among active cedulas), tax year, place of issue, inputs, every computed amount, `breakdown` jsonb (`v` 1), collecting officer snapshot, lifecycle and print columns |
| `cedula_templates`       | Name (unique per barangay ignoring case), exempt flag, income figures, active flag                                                                                                                                            |
| `blotters`               | `blotter_number`, incident type, `incident_at`, place, narrative, status (`open`, `resolved`, `cancelled`), recorded, resolved, and cancelled actor and time, resolution notes, cancel reason                                 |
| `blotter_parties`        | Blotter, role (`complainant`, `respondent`), resident or name and address (check constraint), position                                                                                                                        |
| `blotter_photos`         | Blotter, private path, MIME type, size, uploader, soft delete with `deleted_by`                                                                                                                                               |
| `indigency_certificates` | Resident, `certificate_number`, purpose, signatory snapshots, lifecycle and print columns                                                                                                                                     |

All are tenant tables with indexes starting with `barangay_id`. Cedulas and indigency certificates extend `IssuedDocument`.

### Part B flags and permissions

| Flag                          | Effect when off                                                                                  |
| ----------------------------- | ------------------------------------------------------------------------------------------------ |
| `cedula`                      | Every `/cedulas` route (including the CTC lookup) answers 404; the CTC picker on forms is hidden |
| `cedula-templates`            | Preset routes answer 404; the form offers no presets                                             |
| `cedula-interest` (off)       | No interest; the breakdown records `interest_applied: false`                                     |
| `blotter`                     | Every `/blotters` route answers 404; resident tab, counter, and the complaint hold are off       |
| `blotter-photo-attachment`    | Photo routes answer 404; the photo section is hidden                                             |
| `indigency-certificate` (off) | Every `/indigency-certificates` route answers 404                                                |

| Permission                         | Allows                                                       | Built-in roles                                           |
| ---------------------------------- | ------------------------------------------------------------ | -------------------------------------------------------- |
| `cedula.view`                      | Cedula list, view, CTC lookup, counters                      | every barangay role                                      |
| `cedula.issue`                     | Issue, quote, print, presets                                 | barangay-admin, treasurer, clerk                         |
| `cedula.release` / `cedula.cancel` | Bulk release / cancel                                        | barangay-admin, treasurer                                |
| `blotter.view`                     | List, entry with narrative and photos, resident tab, counter | barangay-admin, secretary, clerk (changed, decision D14) |
| `blotter.manage`                   | Record, edit, cancel, photos                                 | barangay-admin, secretary, clerk                         |
| `blotter.resolve`                  | Resolve                                                      | barangay-admin, secretary                                |
| `clearances.*`                     | Indigency certificates, like clearances                      | as in Part A                                             |

### Part B decisions

- **Community tax (D6).** See ADR 0008. Individuals only; juridical persons wait for D16.
- **Cedula numbers.** `CTC-2026-000001` from the allocator in the issue transaction. The pre-printed form number is optional; when present it is the CTC number offered to clearances and ID cards.
- **Collecting officer.** The barangay treasurer signatory is captured as "certified by" at issue.
- **Blotter privacy.** `blotter.view` is no longer part of every role. The narrative and resolution notes are redacted from audit rows (`AuditRedactor`), and audit rows hold party resident IDs and counts, never typed names. Lists show number, date, type, party counts, and status only.
- **Complaint hold (D8).** `BlotterHoldChecker`: a resident is held while they are a respondent in an open blotter. Resolved and cancelled blotters do not hold; complainants are never held. The checker returns no holds when `unresolved-complaint-hold` (which requires `blotter`) is off. Applied at save to barangay clearances and ID cards (`resident_on_hold`). Cedulas and indigency certificates are not held.
- **Picker.** `GET /residents/search?for_issuance=1` adds `on_hold` to each option. Held residents are shown with an "Unresolved complaint" badge instead of being hidden, so staff can explain the refusal; saving is still refused. Clearance and ID card forms ask for it.
- **Photos.** File names are random ULIDs under `StoragePaths::blotterPhoto()`; the uploaded file name is not stored. Removed photos stay on disk for the record and stop being served.
- **Indigency (D17).** One built-in layout; no templates or placeholders. No fee.
