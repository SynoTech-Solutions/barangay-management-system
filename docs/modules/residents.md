# Residents

The resident registry: one record per person living in the barangay, with family, education, work history, sectors, and a photo. Later modules (certificates, finance, SMS) pick residents through the shared picker described below.

## What it does

| Area               | Behavior                                                                                                                                                                                         |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Register and edit  | Full profile form with tabs: Personal, Address & contact, Government IDs & voter, Sectors, Family, Education & work, Photo. Family, education, work, and photo tabs appear after the first save. |
| Search             | List filters and the picker parse `Last, First` and match resident numbers.                                                                                                                      |
| Full name          | `Last, First Middle Extension`, trimmed with repeated spaces collapsed (`NameNormalizer`).                                                                                                       |
| Spouse             | Symmetric link kept in one transaction (`SpouseLinker`). Changing or clearing a spouse clears the old partner's back-link; each partner change is audited.                                       |
| Photo              | Upload, replace, remove. Private storage, served through an authorized route.                                                                                                                    |
| Sectors            | Join table `resident_sector` to `lookup_values` of type `sector`.                                                                                                                                |
| Family members     | Free-text members or links to another resident.                                                                                                                                                  |
| Education and work | Lookup-based rows with optional dates.                                                                                                                                                           |
| Quick add          | Last and first name plus optional birthdate; record marked incomplete. JSON response for pickers.                                                                                                |
| Picker             | `GET /residents/search` plus the `ResidentPicker` Vue component.                                                                                                                                 |
| List               | Server-side filters, sort, pagination, CSV export.                                                                                                                                               |
| Bulk purok change  | Selected residents move to one purok in one transaction, one audit row per changed resident.                                                                                                     |
| Remove and restore | Soft delete with actor, time, and audit. Administrators can list removed residents and restore them.                                                                                             |
| Puroks             | Barangay purok list: add, rename, hide, restore, delete when unused.                                                                                                                             |
| Relationship types | Lookup type `relationship` in Administration → Lookup values.                                                                                                                                    |
| Printable profile  | Print-friendly page rendered by Vue; nothing is written to disk.                                                                                                                                 |
| Barangay ID cards  | Issue, edit while pending, bulk release and cancel, ledger with print view, card print with reprint tracking. Shares the issued-document lifecycle in `certificates.md`.                         |
| Color tagging      | Separate tenant tables, bulk change from the list, status charts. Off by default.                                                                                                                |
| Statistics         | Counts by sex, civil status, age band, circumstance, purok, and sector.                                                                                                                          |
| Resident reports   | List filters plus age range and one lookup attribute, CSV export, print view.                                                                                                                    |
| Blank intake form  | Legal-size printable form with the sections enabled by flags.                                                                                                                                    |

Not built: fingerprints (see ADR 0005) and saved grid layouts (`grid-layout-persistence`). Texting residents uses the resident-list "Send SMS" and "Add to SMS group" actions and the profile "Send SMS" button; see `sms.md`. Central staff work in a barangay through the central barangay switcher (`docs/platform/tenancy.md`).

## Data placement

All tables are tenant tables: `barangay_id` is not null, indexes start with it, and models use `BelongsToBarangay`.

| Table                     | Contents                                                                                                                                                  |
| ------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `puroks`                  | Name (unique per barangay, case-insensitive), active flag                                                                                                 |
| `residents`               | Profile, address, contact, government IDs, voter data, flags, deceased data, spouse, photo path, incomplete flag, created/updated/deleted by, soft delete |
| `resident_sector`         | Resident to sector lookup value                                                                                                                           |
| `family_members`          | Relationship, full name, birthdate, optional `related_resident_id`, soft delete with actor                                                                |
| `resident_educations`     | School, course, start and end dates, soft delete with actor                                                                                               |
| `resident_work_histories` | Company, position, employment status, start and end dates, current flag, soft delete with actor                                                           |

Lookups (title, nationality, religion, blood type, body type, health issue, profession, skill, income bracket, residence type, school, course, company, position, employment status, relationship, sector) are foreign keys to `lookup_values`. Birthplace is a foreign key to `municipalities`. Region, province, and municipality come from the barangay and are not copied onto residents.

Name search uses a `pg_trgm` GIN index on `full_name`. The duplicate check uses an index on `(barangay_id, lower(last_name), lower(first_name), birthdate)`.

Photos are stored on the private `local` disk under `StoragePaths::residentPhoto()` and served only by `GET /residents/{resident}/photo`, which checks the barangay, `residents.view`, and `resident-photo`.

## Flags

| Flag                                    | Effect when off                                                                            |
| --------------------------------------- | ------------------------------------------------------------------------------------------ |
| `resident-photo`                        | Photo routes answer 404; photo tab and images are hidden                                   |
| `sector-tagging`                        | Sectors tab and filter hidden; saves leave sector links untouched; sector filter ignored   |
| `family-member-linking`                 | Family members are free text only; `related_resident_id` is rejected                       |
| `resident-work-education`               | Education and work routes answer 404; tabs hidden                                          |
| `resident-quick-add`                    | `POST /residents/quick-add` answers 404; picker hides "Register a new person"              |
| `resident-bulk-edit`                    | Bulk purok route answers 404; list checkboxes hidden                                       |
| `resident-profile-print`                | Print route answers 404; print button hidden                                               |
| `grid-excel-export`                     | CSV export answers 404                                                                     |
| `resident-id-cards`                     | Every `/id-cards` route answers 404; sidebar entry and "Issue barangay ID" hidden          |
| `issuance-requires-photo`               | ID cards and barangay clearances can be issued to residents without a photo                |
| `id-card-validity`                      | Card front shows no "Valid until" line                                                     |
| `pvc-id-printing`                       | Card print uses the paper sheet layout instead of one card per CR80 page                   |
| `political-color-tagging` (default off) | Color categories, bulk color, status charts answer 404; color is never sent to the browser |
| `blank-profile-form`                    | Blank intake form answers 404                                                              |

Non-GET requests to a disabled feature follow the platform rule: JSON clients get 404, browser form posts get a redirect with an error toast.

## Permissions

| Permission                             | Allows                                       | Built-in roles                   |
| -------------------------------------- | -------------------------------------------- | -------------------------------- |
| `residents.view`                       | List, show, picker, photo, print             | every barangay role              |
| `residents.create`                     | New resident, quick add                      | barangay-admin, secretary, clerk |
| `residents.update`                     | Edit profile, family, education, work, photo | barangay-admin, secretary, clerk |
| `residents.delete`                     | Remove, list removed, restore                | barangay-admin                   |
| `residents.bulk-edit`                  | Bulk purok change                            | barangay-admin, secretary        |
| `residents.export`                     | CSV export                                   | barangay-admin, secretary        |
| `puroks.manage`                        | Purok list                                   | barangay-admin, secretary        |
| `id-cards.issue`                       | Issue, edit pending cards, print cards       | barangay-admin, secretary, clerk |
| `id-cards.release` / `id-cards.cancel` | Bulk release / bulk cancel                   | barangay-admin, secretary        |
| `reports.view` (new)                   | Statistics and resident reports              | every barangay role              |
| `political-tags.view`                  | See color tags and status charts             | barangay-admin only              |
| `political-tags.manage`                | Color categories and bulk color change       | barangay-admin only              |

The ID card ledger needs only `residents.view`.

## Decisions

- **Resident number.** `DocumentNumberAllocator::next(DocumentKind::Resident, year)` inside the create transaction (`RES-000001`). Opening the form never consumes a number; a failed save rolls the number back.
- **Required fields.** Last name, first name, gender, and purok on the full form; last and first name on quick add. A mobile number is optional because many residents have none.
- **Duplicates.** On create and quick add, an active resident in the same barangay with the same last name, first name (case and spacing ignored), and birthdate (both unknown counts as equal) triggers a `duplicate` validation error. Sending `confirm_duplicate=true` saves anyway. Removed residents never match.
- **Birthdate** is nullable. There is no `0001-01-01` sentinel.
- **Age and senior status** are computed by `ResidentAge` from a reference date passed in by the caller. Senior by age (60+, `ResidentAge::SENIOR_CITIZEN_AGE`) is derived and shown; "senior citizens association member" is a separate manual flag.
- **Flags default to false**, including PWD.
- **Mobile numbers** are validated with `PhilippineMobileNumber::PATTERN` and stored as `09XXXXXXXXX`.
- **Facebook links** are accepted only as http or https URLs and opened with `rel="noopener noreferrer nofollow"`.
- **Height and weight** are numbers in centimeters and kilograms instead of free text.
- **Voter, permanent, deceased dependencies.** Voter ID and precinct are cleared when the resident is not a registered voter; the alternate address is cleared for permanent residents; date and cause of death are cleared when not deceased. Non-permanent residents may still be registered voters.
- **Editing** sets `updated_by` and `updated_at` only. `created_by` and `created_at` are never rewritten. Editing a quick-added record clears `is_incomplete`.
- **Family members** use the linked model (ADR 0005). A linked member copies the relative's name and birthdate at save time and shows the relative's live name. A resident can be linked once per profile. Birthdates are real dates, not birth years.
- **Education and work dates** are dates. The end date is saved whether or not a start date is present; when both exist, the end cannot be before the start. A current job has no end date.
- **Photos** accept JPEG, PNG, and WebP up to `ResidentPhotoRequest::PHOTO_MAX_KILOBYTES` and `PHOTO_MAX_DIMENSION` pixels. Images are stored as uploaded, without resizing. The audit row records only the redacted path, never image bytes.
- **Puroks** are hidden instead of deleted when any resident (including removed ones) references them. Renaming never moves a purok to another barangay.
- **Removal** is a soft delete with actor, time, and an audit row, and keeps child rows. Removed residents disappear from lists, the picker, and route lookups.
- **CSV export** streams rows in chunks, follows the list filters, prefixes cells that start with `=`, `+`, `-`, or `@` with `'`, leaves out government ID numbers, and writes an `exported` audit row with the filters.
- **Audit.** Create, update (changed fields only), delete, restore, photo change, bulk purok change, and purok changes have their own actions. Family, education, and work rows use the generic created/updated/deleted actions. Government ID numbers and photo paths are redacted.

## Barangay ID cards

| Item               | Rule                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| ------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Table              | `id_cards`: resident, `card_number`, status (`issued` = for releasing, `released`, `cancelled`), CTC number, place, and date, OR number, amount (`decimal(12,2)`, not negative), signatory snapshots, issue, release, and cancel actor and time, cancel reason, `print_count`, `last_printed_at`                                                                                                                                                                                                                                                          |
| Number             | `DocumentNumberAllocator::next(DocumentKind::IdCard, year)` inside the issue transaction (`ID-2026-000001`). A rejected issue rolls the number back.                                                                                                                                                                                                                                                                                                                                                                                                      |
| Signatories        | Secretary ("verified by") and punong barangay ("certified by") names and titles are copied onto the card at issue time; later signatory changes do not alter issued cards.                                                                                                                                                                                                                                                                                                                                                                                |
| Blocks             | Deceased residents (`resident_deceased`); removed residents (validation); no photo while `issuance-requires-photo` is on (`resident_photo_required`); respondents in open blotters while `unresolved-complaint-hold` is on (`resident_on_hold`). CTC fields can be filled from the resident's issued cedulas.                                                                                                                                                                                                                                             |
| OR number          | Optional. Unique per barangay among ID cards, barangay clearances, and business clearances that are not cancelled (partial unique index per table plus validation).                                                                                                                                                                                                                                                                                                                                                                                       |
| Editing            | Only cards for releasing. Released and cancelled cards answer `document_not_editable`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| Release and cancel | Bulk over selected cards. Every selected card is checked under a row lock; if any card is not for releasing, the whole request is rejected and nothing changes (`documents_not_pending`, listing the card numbers). Cancel needs a reason. One audit row per card. With `document-release-step` off, cards are released when issued and the release route answers 404.                                                                                                                                                                                    |
| Ledger             | Filters: status, issue date range, resident name or card number. Print view titles follow the status filter and print at most `IdCardLedgerQuery::PRINT_LIMIT` rows with a notice when cut.                                                                                                                                                                                                                                                                                                                                                               |
| Card print         | Front and back on CR80 (85.6 × 53.98 mm). Every print page load increments `print_count` and writes `id-card-printed`. Cancelled cards cannot be printed (`document_cancelled`); released cards can be reprinted.                                                                                                                                                                                                                                                                                                                                         |
| Card front         | Barangay, municipality, and province header; title and ID ordinance number from the barangay profile; photo; name; address (purok, barangay, municipality, province when `default-address` is on, otherwise the street address); birth date and place; sex; civil status; nationality; resident and card numbers; QR code of the resident number; punong barangay signature image (embedded as a data URI when stored), name, and title; bearer signature line; "Valid until" = issue date + `BMS_ID_CARD_VALIDITY_MONTHS` when `id-card-validity` is on. |
| Card back          | Fixed statement, "If found, please return to …", telephone when set, mayor signature, name, and title, resident number. No barcode is drawn (no barcode library; plan D3); the front QR code carries the same number.                                                                                                                                                                                                                                                                                                                                     |
| Layout             | `pvc-id-printing` switches between a paper sheet (A4) and one card per CR80 page. It does not depend on `id-card-validity`.                                                                                                                                                                                                                                                                                                                                                                                                                               |

## Political color tagging

**Legal basis required.** Color categories are local political groupings, which makes a resident's tag sensitive personal information. `political-color-tagging` must stay off in every barangay until the product owner records the legal basis, consent approach, and retention rule. This document is not legal advice.

- Data lives only in `color_categories` (barangay-defined name and `#rrggbb` color) and `resident_political_tags` (one row per resident). Nothing is stored on `residents`, so the data can be deleted wholesale.
- Colors reach the browser only when the flag is on and the user holds `political-tags.view`: the resident list color column, the resident page badge, and the status charts. The CSV export, profile print, resident reports, and ID cards never include them.
- Bulk color change from the resident list needs `political-tags.manage`, `political-color-tagging`, and `resident-bulk-edit`. One `political-tag-changed` audit row per changed resident.
- Status by color and by purok count living, registered voters with a tag, excluding removed residents. Charts are plain CSS bars.
- `php artisan bms:political-tags:purge --barangay=<PSGC>` asks for confirmation, deletes every tag and category of that barangay in one transaction, and writes a `political-tags-purged` audit row with the counts.

See ADR 0006.

## Statistics and reports

- Statistics (`/reports/statistics`) count living residents and show deceased residents as a separate total. Removed residents are never counted. Incomplete (quick-added) records are counted, with unknown sex, civil status, or age in a "Not recorded" bucket.
- Counts are SQL `GROUP BY` queries in `ResidenceStatistics`. Ages are grouped by year in SQL and folded into bands by `AgeBands` (lower bounds 0, 5, 10, 15, 18, 25, 35, 45, 60, 80).
- Senior by age uses `ResidentAge::SENIOR_CITIZEN_AGE`; the association membership flag is counted separately.
- Resident reports (`/reports/residents`) accept the list filters plus `age_from` and `age_to` (whole years, inclusive) and one lookup attribute with any of several values. Export reuses the CSV export (`residents.export` plus `grid-excel-export`); the print view lists at most `ReportController::PRINT_LIMIT` rows.
- Length of stay is a calendar difference (`LengthOfStay`), not 360-day years.

## Blank intake form

`/residents/blank-form` (`blank-profile-form`, `residents.view`) prints a Legal-size form with the barangay header from the database and empty fields for the resident form sections. Sector and education/work sections appear only when their flags are on. No site-specific text ships with it.

## Picker contract

`GET /residents/search` (`residents.view`) returns `{ data: ResidentOption[], meta }`.

| Parameter          | Meaning                                                                                                                                                                                                     |
| ------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `q`                | `Last, First` (each part matched with "contains"), or words that must all appear in the full name, or a resident number prefix. Empty returns nothing.                                                      |
| `include_deceased` | `1` to include deceased residents. Default excludes them.                                                                                                                                                   |
| `exclude_id`       | Leave out one resident, for example the resident being edited.                                                                                                                                              |
| `for_issuance`     | `1` to add `on_hold` (respondent in an open blotter while `unresolved-complaint-hold` is on) to every option. Held residents are listed with a badge, not hidden; the issuing service refuses them at save. |
| `per_page`, `page` | Pagination through `PaginationSettings::clamp()`.                                                                                                                                                           |

`ResidentOption`: `id`, `resident_number`, `full_name`, `birthdate`, `age`, `purok`, `is_deceased`, and `on_hold` when `for_issuance` is set. Removed residents and other barangays never appear.

The picker component takes a `forIssuance` prop for this. Clearance and ID card forms set it.

`resources/js/components/residents/ResidentPicker.vue` wraps it: `v-model` holds a `ResidentOption | null`; props `includeDeceased`, `excludeId`, `allowQuickAdd`, `placeholder`, `id`.

## Show page extension point

`residents/Show.vue` builds its tabs from a `tabs` list of `{ key, label, visible }`. Certificate and finance modules add an entry and a matching panel. The "Clearances" tab (`ResidentClearancesPanel`) is the first one; the controller sends `clearances: null` when `barangay-clearance` is off or the user lacks `clearances.view`.
