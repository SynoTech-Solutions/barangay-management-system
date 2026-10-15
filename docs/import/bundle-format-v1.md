# Import bundle format, version 1

An import bundle is the only way data from another system enters BMS. A separate converter turns a source export into a bundle; `php artisan bms:import:bundle` validates it and writes it into one onboarded barangay. The importer knows nothing about where the data came from. Operator steps: [modules/import.md](../modules/import.md). Decision: [ADR 0014](../adr/0014-versioned-import-bundle.md).

The contract is published as JSON Schema (draft 2020-12) in `resources/import-bundle/v1/`: `manifest.schema.json`, `common.schema.json` (shared definitions), and one `<entity>.schema.json` per entity. The schemas are the source of truth; this page explains them.

## Layout

```text
bundle/
  manifest.json
  residents.jsonl
  id_cards.jsonl
  ...
  files/
    residents/R-1.jpg
    templates/permit.pdf
```

| Part             | Rule                                                                                                                                                 |
| ---------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| `manifest.json`  | One JSON object, described below. Required.                                                                                                          |
| Entity files     | JSON Lines, UTF-8: one JSON object per line, no trailing commas. Blank lines are ignored. File names are listed in the manifest and sit in the root. |
| `files/`         | Binary attachments. Records refer to them by path relative to `files/`.                                                                              |
| Omitted entities | An entity missing from the manifest is simply not imported.                                                                                          |

## Manifest

```json
{
    "format": "bms-import-bundle",
    "version": 1,
    "barangay_psgc": "9901010001",
    "generated_at": "2026-10-01T08:00:00+08:00",
    "generator": { "name": "example-converter", "version": "1.0.0" },
    "entities": {
        "puroks": { "file": "puroks.jsonl", "count": 2 },
        "residents": { "file": "residents.jsonl", "count": 1250 }
    }
}
```

| Field           | Required | Rule                                                                                                                           |
| --------------- | -------- | ------------------------------------------------------------------------------------------------------------------------------ |
| `format`        | yes      | Always `bms-import-bundle`.                                                                                                    |
| `version`       | yes      | Bundle format version. This importer reads `1`; any other value is rejected before anything else is read.                      |
| `barangay_psgc` |          | Target barangay. `--barangay` on the command wins; the command warns when the two differ.                                      |
| `generated_at`  | yes      | RFC 3339 date-time with offset.                                                                                                |
| `generator`     | yes      | `name` and `version` of the tool that wrote the bundle. Stored on the import run.                                              |
| `entities`      | yes      | Entity name → `file` (a `.jsonl` name in the bundle root) and `count` (number of non-blank lines). Unknown names are rejected. |

## Value rules

Records already hold values in this system's vocabulary. The importer does not translate codes, guess formats, or repair source data; it only trims and collapses whitespace in short text.

| Kind          | Rule                                                                                                                                                                                   |
| ------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `source_key`  | Every record has one: an opaque string, 1 to 191 characters, no leading or trailing space, unique per entity within the bundle. It is the idempotency key across runs; keep it stable. |
| Key reference | A field ending in `_key` holds the `source_key` of a record in another entity (named in each section). It resolves against this run and every earlier run into the same barangay.      |
| Lookup label  | Lookup values are referenced by label within their type, for example `"religion": "Roman Catholic"`. See "Lookups" below.                                                              |
| Date          | `YYYY-MM-DD`.                                                                                                                                                                          |
| Date-time     | RFC 3339 with an offset, for example `2024-03-05T09:15:00+08:00`. Stored in UTC. Document years (numbering, expiry) use the date in the given offset.                                  |
| Money         | Decimal string with exactly two decimals, no sign, no separators: `"1250.00"` (ADR 0007). Fields marked "money > 0" must be positive.                                                  |
| Status        | Values of the application enums, listed per field.                                                                                                                                     |
| Names         | Already split and cleaned (`last_name`, `first_name`, `middle_name`, `name_extension`).                                                                                                |
| Mobile        | `09XXXXXXXXX`, `639XXXXXXXXX`, or `+639XXXXXXXXX`, no separators (what `PhilippineMobileNumber` accepts).                                                                              |
| Booleans      | JSON `true` or `false`. Omitted means `false`, except `is_active`, which defaults to `true`.                                                                                           |
| Optional      | Optional fields may be omitted or `null`. Empty strings are not a substitute for `null` in lookup labels and key references.                                                           |
| Unknown field | Rejected. Every object in every schema has `additionalProperties: false`.                                                                                                              |
| File path     | Relative to `files/`. Segments use letters, digits, `.`, `_`, and `-` and never start with a dot, so absolute paths, `..`, backslashes, and drive letters are rejected.                |

### Document numbers

`source_number` is the original display number of a document (`source_control_number` for the second number of clearances). The importer keeps it as the display number when it is at most 32 characters and unique in the barangay; otherwise it assigns a new number, stores the original in the `source_number` column, and reports `number_replaced`. Without a source number the record gets the next number.

After the run, each document sequence is raised (never lowered) to the highest imported counter per kind and year so new numbers continue without collision. The counter is the trailing digits of the number; the year is the first 4-digit segment between 1900 and 2999 that is not the last segment (`BCC-2024-000007` → 2024, 7), otherwise the document's issue year. `BCC-2024-000007` makes the next control number `BCC-2024-000008`.

### `import_note`

Free text up to 255 characters, stored on the record. The importer appends its own notes (OR or form-number conflicts) and truncates to 255 characters.

### Lookups

A label is normalized (whitespace collapsed, case ignored) and matched first to a shared value of its type, then to a barangay value. Without a match the importer creates a barangay value. There is no synonym list: send the label you want matched. Lookup types: `blood-type`, `body-type`, `course`, `employment-status`, `health-issue`, `income-bracket`, `nationality`, `position`, `profession`, `religion`, `school`, `skill`, `title`, `residence-type`, `company`, `relationship`, `sector`, `clearance-type`, `clearance-purpose`, `business-category`, `business-nature`, `business-ownership-type`, `blotter-incident-type`, `certificate-purpose`, `form-template-type`.

### Issued documents

ID cards, clearances, indigency certificates, cedulas, and issued forms share these fields: `status` (`issued`, `released`, `cancelled`), `issued_at` (required), `released_at`, `cancelled_at`, `cancel_reason`, `verified_by_name`, `certified_by_name`. A missing `released_at` or `cancelled_at` defaults to `issued_at`; a missing `cancel_reason` gets a standard text.

### OR numbers

Every OR number is normalized (trimmed, upper case) and registered in `official_receipts` (ADR 0010). The first active document with a number keeps it. A later document with the same active number is imported with the OR cleared and a note in `import_note` (`or_number_conflict`, a warning). Other collections require their OR, so a conflict rejects that record. Cancelled documents never block a number.

## Reference rules

1. A `_key` field names its target entity in each section; there is no cross-entity key space.
2. References resolve through the id map (`import_id_map`), so a target imported by an earlier run counts.
3. A required reference that does not resolve rejects the record (`dangling_reference`, an error).
4. An optional reference that does not resolve is dropped with a warning (`dangling_reference`); the record is imported.
5. Self references (`spouse_key`, account `parent_key`) are allowed. List account parents before their children.
6. Nested keys: voucher items carry their own `source_key`, unique across all vouchers; liquidation items reference them with `voucher_item_key`.

## Entities

Entities import in this order. `--only` keeps this order and refuses an entity whose dependency is in the bundle but neither selected nor imported by an earlier run.

### 1. `lookups`

Default file `lookups.jsonl`, schema `lookups.schema.json`. Writes `lookup_values`.

| Field        | Type              | Required |
| ------------ | ----------------- | -------- |
| `source_key` | key               | yes      |
| `type`       | lookup type       | yes      |
| `label`      | label             | yes      |
| `sort_order` | integer 0–1000000 |          |
| `is_active`  | boolean           |          |

- Optional. Use it for values no record references, or to set `sort_order`. Records elsewhere reference lookups by label, so this file is never required.
- Matched to a shared value by normalized label (case and repeated spaces ignored), then to a barangay value; otherwise a barangay value is created.

### 2. `puroks`

Default file `puroks.jsonl`, schema `puroks.schema.json`. Writes `puroks`.

| Field        | Type         | Required |
| ------------ | ------------ | -------- |
| `source_key` | key          | yes      |
| `name`       | string ≤ 100 | yes      |
| `is_active`  | boolean      |          |

- Matched by name, ignoring case; otherwise created.

### 3. `users`

Default file `users.jsonl`, schema `users.schema.json`. Writes `users`, `user_roles`.

| Field         | Type                  | Required |
| ------------- | --------------------- | -------- |
| `source_key`  | key                   | yes      |
| `username`    | string ≤ 64 (pattern) | yes      |
| `name`        | string ≤ 255          | yes      |
| `designation` | string ≤ 255          |          |
| `role`        | barangay role         | yes      |
| `created_at`  | date-time             |          |
| `password`    | forbidden             |          |

- Created inactive with a random unusable password. There is no password field; a record that has one fails validation.
- `role` is a barangay-scoped `SystemRole` value. Central roles are not accepted.
- `username` is lowercased. A clash with any existing account gets `-<PSGC>` appended (`username_renamed`).

### 4. `color_categories`

Default file `color_categories.jsonl`, schema `color_categories.schema.json`. Writes `color_categories`.

| Field        | Type             | Required |
| ------------ | ---------------- | -------- |
| `source_key` | key              | yes      |
| `name`       | string ≤ 100     | yes      |
| `color_hex`  | string (pattern) | yes      |
| `is_active`  | boolean          |          |

- Imported only when `political-color-tagging` is on for the barangay at import time; otherwise skipped (`color_tag_skipped`). Matched by name.

### 5. `residents`

Default file `residents.jsonl`, schema `residents.schema.json`. Writes `residents`, `resident_sector`, `resident_political_tags`, private disk. References: puroks, color_categories.

| Field                      | Type                                        | Required |
| -------------------------- | ------------------------------------------- | -------- |
| `source_key`               | key                                         | yes      |
| `source_number`            | string ≤ 64                                 |          |
| `import_note`              | string ≤ 255                                |          |
| `last_name`                | string ≤ 100                                | yes      |
| `first_name`               | string ≤ 100                                | yes      |
| `middle_name`              | string ≤ 100                                |          |
| `name_extension`           | string ≤ 10                                 |          |
| `nickname`                 | string ≤ 100                                |          |
| `birthdate`                | date                                        |          |
| `birthplace_psgc`          | string (pattern)                            |          |
| `gender`                   | `male`, `female`                            |          |
| `civil_status`             | `single`, `married`, `widowed`, `separated` |          |
| `height_cm`                | number                                      |          |
| `weight_kg`                | number                                      |          |
| `title`                    | label                                       |          |
| `nationality`              | label                                       |          |
| `religion`                 | label                                       |          |
| `blood_type`               | label                                       |          |
| `body_type`                | label                                       |          |
| `health_issue`             | label                                       |          |
| `profession`               | label                                       |          |
| `skill`                    | label                                       |          |
| `income_bracket`           | label                                       |          |
| `residence_type`           | label                                       |          |
| `national_id`              | string ≤ 32                                 |          |
| `drivers_license`          | string ≤ 32                                 |          |
| `tin`                      | string ≤ 32                                 |          |
| `sss_gsis`                 | string ≤ 32                                 |          |
| `pagibig`                  | string ≤ 32                                 |          |
| `philhealth`               | string ≤ 32                                 |          |
| `voter_id`                 | string ≤ 32                                 |          |
| `landline_number`          | string ≤ 32                                 |          |
| `precinct_number`          | string ≤ 16                                 |          |
| `is_registered_voter`      | boolean                                     |          |
| `is_living_with_parents`   | boolean                                     |          |
| `is_senior_citizen_member` | boolean                                     |          |
| `is_pwd`                   | boolean                                     |          |
| `is_single_parent`         | boolean                                     |          |
| `is_indigent`              | boolean                                     |          |
| `purok_key`                | key ref                                     |          |
| `street_address`           | string ≤ 255                                |          |
| `is_permanent_resident`    | boolean                                     |          |
| `residing_since`           | date                                        |          |
| `alternate_address`        | string ≤ 1000                               |          |
| `mobile_number`            | mobile                                      |          |
| `email`                    | email ≤ 255                                 |          |
| `facebook_url`             | string ≤ 255 (pattern)                      |          |
| `is_deceased`              | boolean                                     |          |
| `deceased_on`              | date                                        |          |
| `cause_of_death`           | string ≤ 255                                |          |
| `spouse_key`               | key ref                                     |          |
| `photo`                    | file path                                   |          |
| `photo_taken_at`           | date-time                                   |          |
| `sectors`                  | array                                       |          |
| `color_category_key`       | key ref                                     |          |
| `is_incomplete`            | boolean                                     |          |
| `created_at`               | date-time                                   |          |
| `updated_at`               | date-time                                   |          |
| `deleted_at`               | date-time                                   |          |

- `full_name` is built by the importer (`Last, First Middle Ext`). Both names empty: `missing_name`. One empty name sets `is_incomplete`.
- `birthdate` after the import day is dropped (`future_birthdate`). `birthplace_psgc` must be a municipality PSGC code already loaded (`unknown_birthplace` otherwise).
- `voter_id` and `precinct_number` are kept only when `is_registered_voter`; death data only when `is_deceased`; `alternate_address` only when not `is_permanent_resident`.
- `sectors` are labels of the `sector` lookup type. `spouse_key` links both residents after the entity finishes.
- `photo`: JPEG, PNG, or WebP up to the resident photo limit; anything else is a warning and the resident is imported without a photo.
- `color_category_key` follows the color-tag flag rule above.

### 6. `family_members`

Default file `family_members.jsonl`, schema `family_members.schema.json`. Writes `family_members`. References: residents.

| Field                  | Type              | Required |
| ---------------------- | ----------------- | -------- |
| `source_key`           | key               | yes      |
| `resident_key`         | key ref           | yes      |
| `relationship`         | label             | yes      |
| `full_name`            | string ≤ 320      | yes      |
| `birthdate`            | date              |          |
| `birth_year`           | integer 1800–2200 |          |
| `related_resident_key` | key ref           |          |
| `deleted_at`           | date-time         |          |

- `relationship` is a `relationship` lookup label. Use `birth_year` when only the year is known; leave `birthdate` empty then.

### 7. `educations`

Default file `educations.jsonl`, schema `educations.schema.json`. Writes `resident_educations`. References: residents.

| Field          | Type      | Required |
| -------------- | --------- | -------- |
| `source_key`   | key       | yes      |
| `resident_key` | key ref   | yes      |
| `school`       | label     | yes      |
| `course`       | label     | yes      |
| `started_on`   | date      |          |
| `ended_on`     | date      |          |
| `deleted_at`   | date-time |          |

- `school` and `course` are lookup labels.

### 8. `work_histories`

Default file `work_histories.jsonl`, schema `work_histories.schema.json`. Writes `resident_work_histories`. References: residents.

| Field               | Type      | Required |
| ------------------- | --------- | -------- |
| `source_key`        | key       | yes      |
| `resident_key`      | key ref   | yes      |
| `company`           | label     | yes      |
| `position`          | label     | yes      |
| `employment_status` | label     | yes      |
| `started_on`        | date      |          |
| `ended_on`          | date      |          |
| `is_current`        | boolean   |          |
| `deleted_at`        | date-time |          |

- `company`, `position`, and `employment_status` are lookup labels. `ended_on` is ignored when `is_current`.

### 9. `id_cards`

Default file `id_cards.jsonl`, schema `id_cards.schema.json`. Writes `id_cards`, `official_receipts`. References: residents.

| Field               | Type                              | Required |
| ------------------- | --------------------------------- | -------- |
| `source_key`        | key                               | yes      |
| `resident_key`      | key ref                           | yes      |
| `source_number`     | string ≤ 64                       |          |
| `import_note`       | string ≤ 255                      |          |
| `status`            | `issued`, `released`, `cancelled` | yes      |
| `issued_at`         | date-time                         | yes      |
| `released_at`       | date-time                         |          |
| `cancelled_at`      | date-time                         |          |
| `cancel_reason`     | string ≤ 255                      |          |
| `verified_by_name`  | string ≤ 255                      |          |
| `certified_by_name` | string ≤ 255                      |          |
| `ctc_number`        | string ≤ 32                       |          |
| `ctc_place`         | string ≤ 255                      |          |
| `ctc_date`          | date                              |          |
| `or_number`         | string ≤ 32                       |          |
| `amount`            | money                             |          |

- OR numbers go through the receipt registry (see "OR numbers" below).

### 10. `barangay_clearances`

Default file `barangay_clearances.jsonl`, schema `barangay_clearances.schema.json`. Writes `barangay_clearances`, `official_receipts`. References: residents.

| Field                   | Type                              | Required |
| ----------------------- | --------------------------------- | -------- |
| `source_key`            | key                               | yes      |
| `resident_key`          | key ref                           | yes      |
| `source_number`         | string ≤ 64                       |          |
| `import_note`           | string ≤ 255                      |          |
| `source_control_number` | string ≤ 64                       |          |
| `clearance_type`        | label                             | yes      |
| `purpose`               | label                             | yes      |
| `status`                | `issued`, `released`, `cancelled` | yes      |
| `issued_at`             | date-time                         | yes      |
| `released_at`           | date-time                         |          |
| `cancelled_at`          | date-time                         |          |
| `cancel_reason`         | string ≤ 255                      |          |
| `verified_by_name`      | string ≤ 255                      |          |
| `certified_by_name`     | string ≤ 255                      |          |
| `ctc_number`            | string ≤ 32                       |          |
| `ctc_place`             | string ≤ 255                      |          |
| `ctc_date`              | date                              |          |
| `or_number`             | string ≤ 32                       |          |
| `amount`                | money                             |          |
| `doc_stamp`             | money                             |          |
| `expires_on`            | date                              |          |

- `clearance_type` and `purpose` are lookup labels. Without `expires_on`, the barangay's clearance validity rule applies to the issue date.
- Two numbers: `source_number` (clearance) and `source_control_number` (control number).

### 11. `indigency_certificates`

Default file `indigency_certificates.jsonl`, schema `indigency_certificates.schema.json`. Writes `indigency_certificates`. References: residents.

| Field               | Type                              | Required |
| ------------------- | --------------------------------- | -------- |
| `source_key`        | key                               | yes      |
| `resident_key`      | key ref                           | yes      |
| `source_number`     | string ≤ 64                       |          |
| `import_note`       | string ≤ 255                      |          |
| `purpose`           | label                             | yes      |
| `status`            | `issued`, `released`, `cancelled` | yes      |
| `issued_at`         | date-time                         | yes      |
| `released_at`       | date-time                         |          |
| `cancelled_at`      | date-time                         |          |
| `cancel_reason`     | string ≤ 255                      |          |
| `verified_by_name`  | string ≤ 255                      |          |
| `certified_by_name` | string ≤ 255                      |          |
| `amount`            | money                             |          |

- `purpose` is a `certificate-purpose` lookup label.

### 12. `businesses`

Default file `businesses.jsonl`, schema `businesses.schema.json`. Writes `businesses`. References: residents, puroks.

| Field                | Type                 | Required |
| -------------------- | -------------------- | -------- |
| `source_key`         | key                  | yes      |
| `source_number`      | string ≤ 64          |          |
| `import_note`        | string ≤ 255         |          |
| `name`               | string ≤ 150         | yes      |
| `owner_resident_key` | key ref              |          |
| `owner_name`         | string ≤ 255         |          |
| `category`           | label                | yes      |
| `nature`             | label                | yes      |
| `ownership_type`     | label                | yes      |
| `purok_key`          | key ref              |          |
| `street_address`     | string ≤ 255         |          |
| `plate_number`       | string ≤ 32          |          |
| `operating_since`    | date                 |          |
| `capitalization`     | money                |          |
| `gross_receipts`     | money                |          |
| `status`             | `active`, `inactive` |          |
| `registered_at`      | date-time            |          |

- Needs `owner_resident_key` or `owner_name`. Names are unique per barangay; a clash gets ` (2)` appended (`name_taken`).

### 13. `business_clearances`

Default file `business_clearances.jsonl`, schema `business_clearances.schema.json`. Writes `business_clearances`, `official_receipts`. References: businesses, residents.

| Field                   | Type                              | Required |
| ----------------------- | --------------------------------- | -------- |
| `source_key`            | key                               | yes      |
| `business_key`          | key ref                           | yes      |
| `source_number`         | string ≤ 64                       |          |
| `import_note`           | string ≤ 255                      |          |
| `source_control_number` | string ≤ 64                       |          |
| `owner_resident_key`    | key ref                           |          |
| `owner_name`            | string ≤ 255                      |          |
| `business_name`         | string ≤ 150                      |          |
| `business_address`      | string ≤ 255                      |          |
| `status`                | `issued`, `released`, `cancelled` | yes      |
| `issued_at`             | date-time                         | yes      |
| `released_at`           | date-time                         |          |
| `cancelled_at`          | date-time                         |          |
| `cancel_reason`         | string ≤ 255                      |          |
| `verified_by_name`      | string ≤ 255                      |          |
| `certified_by_name`     | string ≤ 255                      |          |
| `ctc_number`            | string ≤ 32                       |          |
| `ctc_place`             | string ≤ 255                      |          |
| `ctc_date`              | date                              |          |
| `or_number`             | string ≤ 32                       |          |
| `amount`                | money                             |          |
| `doc_stamp`             | money                             |          |
| `expires_on`            | date                              |          |

- Business name, address, and owner default to the business record.
- Without `expires_on`, the clearance expires at the end of its issue year.

### 14. `cedulas`

Default file `cedulas.jsonl`, schema `cedulas.schema.json`. Writes `cedulas`. References: residents.

| Field                     | Type                              | Required |
| ------------------------- | --------------------------------- | -------- |
| `source_key`              | key                               | yes      |
| `resident_key`            | key ref                           | yes      |
| `source_number`           | string ≤ 64                       |          |
| `import_note`             | string ≤ 255                      |          |
| `form_number`             | string ≤ 32                       |          |
| `tax_year`                | integer 1900–2999                 |          |
| `place_of_issue`          | string ≤ 255                      |          |
| `is_exempt`               | boolean                           |          |
| `status`                  | `issued`, `released`, `cancelled` | yes      |
| `issued_at`               | date-time                         | yes      |
| `released_at`             | date-time                         |          |
| `cancelled_at`            | date-time                         |          |
| `cancel_reason`           | string ≤ 255                      |          |
| `verified_by_name`        | string ≤ 255                      |          |
| `certified_by_name`       | string ≤ 255                      |          |
| `business_gross_receipts` | money                             |          |
| `salaries`                | money                             |          |
| `real_property_income`    | money                             |          |
| `basic_tax`               | money                             | yes      |
| `business_tax`            | money                             |          |
| `salary_tax`              | money                             |          |
| `real_property_tax`       | money                             |          |
| `additional_tax`          | money                             |          |
| `interest`                | money                             |          |
| `amount`                  | money                             | yes      |

- Amounts are taken as given; the importer does not recompute them. The breakdown is stored with rule `imported`.
- A `form_number` already used by another active cedula is cleared with a note (`form_number_conflict`).

### 15. `blotters`

Default file `blotters.jsonl`, schema `blotters.schema.json`. Writes `blotters`, `blotter_parties`, `blotter_photos`, private disk. References: residents.

| Field                    | Type                            | Required |
| ------------------------ | ------------------------------- | -------- |
| `source_key`             | key                             | yes      |
| `source_number`          | string ≤ 64                     |          |
| `import_note`            | string ≤ 255                    |          |
| `incident_type`          | label                           | yes      |
| `incident_at`            | date-time                       |          |
| `reported_at`            | date-time                       | yes      |
| `place`                  | string ≤ 255                    |          |
| `narrative`              | string ≤ 20000                  | yes      |
| `status`                 | `open`, `resolved`, `cancelled` | yes      |
| `resolved_at`            | date-time                       |          |
| `resolution_notes`       | string ≤ 20000                  |          |
| `cancelled_at`           | date-time                       |          |
| `cancel_reason`          | string ≤ 255                    |          |
| `parties`                | array                           |          |
| `parties[].role`         | `complainant`, `respondent`     | yes      |
| `parties[].resident_key` | key ref                         |          |
| `parties[].name`         | string ≤ 255                    |          |
| `parties[].address`      | string ≤ 255                    |          |
| `photos`                 | array                           |          |

- Each party needs `resident_key` or `name`. A party whose resident is missing keeps its name, or is dropped with a warning.
- `photos`: JPEG, PNG, or WebP up to the blotter photo limit.

### 16. `collection_types`

Default file `collection_types.jsonl`, schema `collection_types.schema.json`. Writes `collection_types`.

| Field        | Type         | Required |
| ------------ | ------------ | -------- |
| `source_key` | key          | yes      |
| `name`       | string ≤ 100 | yes      |
| `is_active`  | boolean      |          |

- Matched by name, ignoring case.

### 17. `other_collections`

Default file `other_collections.jsonl`, schema `other_collections.schema.json`. Writes `other_collections`, `official_receipts`. References: collection_types, residents.

| Field                 | Type         | Required |
| --------------------- | ------------ | -------- |
| `source_key`          | key          | yes      |
| `source_number`       | string ≤ 64  |          |
| `import_note`         | string ≤ 255 |          |
| `collection_type_key` | key ref      | yes      |
| `resident_key`        | key ref      |          |
| `payer_name`          | string ≤ 255 | yes      |
| `or_number`           | string ≤ 32  | yes      |
| `amount`              | money > 0    | yes      |
| `paid_on`             | date         |          |
| `remarks`             | string ≤ 255 |          |
| `recorded_at`         | date-time    | yes      |
| `cancelled_at`        | date-time    |          |
| `cancel_reason`       | string ≤ 255 |          |

- `or_number` is required. A conflict with an active OR rejects the record (`or_number_conflict`).

### 18. `accounts`

Default file `accounts.jsonl`, schema `accounts.schema.json`. Writes `accounts`.

| Field        | Type                           | Required |
| ------------ | ------------------------------ | -------- |
| `source_key` | key                            | yes      |
| `code`       | string ≤ 32 (pattern)          | yes      |
| `name`       | string ≤ 200                   | yes      |
| `kind`       | `group`, `title`, `subsidiary` | yes      |
| `group_type` | `income`, `expense`, `other`   |          |
| `parent_key` | key ref                        |          |
| `remarks`    | string ≤ 255                   |          |
| `is_locked`  | boolean                        |          |
| `is_active`  | boolean                        |          |

- List parents before children. An existing account with the same `code` is reused (`account_kind_mismatch` if its kind differs).
- A group maps to the onboarded group with the same `group_type`; titles need a group or title parent, subsidiaries a title or subsidiary parent (`account_parent_invalid`).

### 19. `budgets`

Default file `budgets.jsonl`, schema `budgets.schema.json`. Writes `budgets`, `budget_versions`, `budget_lines`. References: accounts.

| Field                            | Type                | Required |
| -------------------------------- | ------------------- | -------- |
| `source_key`                     | key                 | yes      |
| `fiscal_year`                    | integer 1900–2999   | yes      |
| `title`                          | string ≤ 100        | yes      |
| `status`                         | `draft`, `approved` | yes      |
| `approved_at`                    | date-time           |          |
| `versions`                       | array               | yes      |
| `versions[].number`              | integer 1–1000      | yes      |
| `versions[].remarks`             | string ≤ 500        |          |
| `versions[].approved_at`         | date-time           |          |
| `versions[].lines`               | array               |          |
| `versions[].lines[].account_key` | key ref             | yes      |
| `versions[].lines[].amount`      | money               | yes      |
| `versions[].lines[].remarks`     | string ≤ 255        |          |

- A fiscal year that already has a budget is skipped (`budget_year_exists`).
- Version numbers are unique per budget. Lines need a posting account (no children, not a group); one line per account per version.
- Without `approved_at` on a version, an approved budget approves its latest version.

### 20. `bank_accounts`

Default file `bank_accounts.jsonl`, schema `bank_accounts.schema.json`. Writes `bank_accounts`.

| Field             | Type         | Required |
| ----------------- | ------------ | -------- |
| `source_key`      | key          | yes      |
| `account_number`  | string ≤ 64  | yes      |
| `account_name`    | string ≤ 150 | yes      |
| `depository_bank` | string ≤ 150 | yes      |
| `branch_address`  | string ≤ 255 |          |
| `is_active`       | boolean      |          |

- Matched by account number.

### 21. `vendors`

Default file `vendors.jsonl`, schema `vendors.schema.json`. Writes `vendors`.

| Field            | Type         | Required |
| ---------------- | ------------ | -------- |
| `source_key`     | key          | yes      |
| `name`           | string ≤ 150 | yes      |
| `address`        | string ≤ 255 | yes      |
| `contact_number` | string ≤ 50  | yes      |
| `email`          | email ≤ 150  |          |
| `contact_person` | string ≤ 150 |          |
| `position`       | string ≤ 100 |          |
| `tin`            | string ≤ 32  |          |
| `is_active`      | boolean      |          |

- Matched by name, ignoring case.

### 22. `vouchers`

Default file `vouchers.jsonl`, schema `vouchers.schema.json`. Writes `vouchers`, `voucher_items`. References: budgets, accounts, vendors, residents.

| Field                         | Type                                                                  | Required |
| ----------------------------- | --------------------------------------------------------------------- | -------- |
| `source_key`                  | key                                                                   | yes      |
| `source_number`               | string ≤ 64                                                           |          |
| `import_note`                 | string ≤ 255                                                          |          |
| `budget_key`                  | key ref                                                               | yes      |
| `payment_type`                | `cash-advance`, `reimbursement`, `others`                             | yes      |
| `status`                      | `prepared`, `verified`, `approved`, `released`, `closed`, `cancelled` | yes      |
| `voucher_date`                | date                                                                  | yes      |
| `vendor_key`                  | key ref                                                               |          |
| `resident_key`                | key ref                                                               |          |
| `payee_name`                  | string ≤ 255                                                          | yes      |
| `payee_address`               | string ≤ 255                                                          |          |
| `payee_tin`                   | string ≤ 32                                                           |          |
| `particulars`                 | string ≤ 500                                                          | yes      |
| `responsibility_center`       | string ≤ 150                                                          |          |
| `office_unit`                 | string ≤ 150                                                          |          |
| `treasurer_name`              | string ≤ 255                                                          |          |
| `bookkeeper_name`             | string ≤ 255                                                          |          |
| `punong_barangay_name`        | string ≤ 255                                                          |          |
| `head_of_appropriations_name` | string ≤ 255                                                          |          |
| `prepared_at`                 | date-time                                                             | yes      |
| `verified_at`                 | date-time                                                             |          |
| `approved_at`                 | date-time                                                             |          |
| `released_at`                 | date-time                                                             |          |
| `closed_at`                   | date-time                                                             |          |
| `cancelled_at`                | date-time                                                             |          |
| `cancel_reason`               | string ≤ 255                                                          |          |
| `items`                       | array                                                                 | yes      |
| `items[].source_key`          | key                                                                   | yes      |
| `items[].account_key`         | key ref                                                               | yes      |
| `items[].description`         | string ≤ 255                                                          | yes      |
| `items[].amount`              | money > 0                                                             | yes      |

- Every item needs an imported account; a missing one rejects the voucher. Item `source_key` values are unique across all vouchers.
- Missing `verified_at`, `approved_at`, and `released_at` are filled from the previous step when the status has passed that step.

### 23. `checks`

Default file `checks.jsonl`, schema `checks.schema.json`. Writes `checks`. References: bank_accounts, vouchers, vendors.

| Field              | Type                                   | Required |
| ------------------ | -------------------------------------- | -------- |
| `source_key`       | key                                    | yes      |
| `bank_account_key` | key ref                                | yes      |
| `voucher_key`      | key ref                                |          |
| `vendor_key`       | key ref                                |          |
| `payee_name`       | string ≤ 255                           | yes      |
| `check_number`     | string ≤ 32                            | yes      |
| `check_date`       | date                                   |          |
| `amount`           | money > 0                              | yes      |
| `remarks`          | string ≤ 255                           |          |
| `status`           | `for-release`, `released`, `cancelled` | yes      |
| `issued_at`        | date-time                              | yes      |
| `released_at`      | date-time                              |          |
| `received_by_name` | string ≤ 150                           |          |
| `cancelled_at`     | date-time                              |          |
| `cancel_reason`    | string ≤ 255                           |          |

- At least 1.00 (`amount_below_minimum`). One active check per bank account and number, and per voucher (`check_conflict`).

### 24. `liquidations`

Default file `liquidations.jsonl`, schema `liquidations.schema.json`. Writes `liquidations`, `liquidation_items`, `official_receipts`. References: vouchers (and their items).

| Field                      | Type                      | Required |
| -------------------------- | ------------------------- | -------- |
| `source_key`               | key                       | yes      |
| `source_number`            | string ≤ 64               |          |
| `import_note`              | string ≤ 255              |          |
| `voucher_key`              | key ref                   | yes      |
| `liquidated_on`            | date                      |          |
| `or_number`                | string ≤ 32               |          |
| `or_date`                  | date                      |          |
| `status`                   | `for-clearing`, `cleared` | yes      |
| `recorded_at`              | date-time                 | yes      |
| `cleared_at`               | date-time                 |          |
| `items`                    | array                     |          |
| `items[].voucher_item_key` | key ref                   | yes      |
| `items[].actual_amount`    | money                     | yes      |

- One liquidation per voucher (`liquidation_conflict`). Items must belong to the same voucher.

### 25. `form_picklists`

Default file `form_picklists.jsonl`, schema `form_picklists.schema.json`. Writes `form_picklists`, `form_picklist_items`.

| Field        | Type         | Required |
| ------------ | ------------ | -------- |
| `source_key` | key          | yes      |
| `name`       | string ≤ 120 | yes      |
| `is_active`  | boolean      |          |
| `items`      | array        |          |

- Matched by name; items are added when missing.

### 26. `form_templates`

Default file `form_templates.jsonl`, schema `form_templates.schema.json`. Writes `form_templates`, `form_template_versions`, `form_template_fields`, `form_pdf_mappings`, private disk. References: form_picklists.

| Field                                       | Type                                                              | Required |
| ------------------------------------------- | ----------------------------------------------------------------- | -------- |
| `source_key`                                | key                                                               | yes      |
| `source_number`                             | string ≤ 64                                                       |          |
| `import_note`                               | string ≤ 255                                                      |          |
| `name`                                      | string ≤ 150                                                      | yes      |
| `type`                                      | label                                                             | yes      |
| `kind`                                      | `rich-text`, `pdf`                                                | yes      |
| `is_active`                                 | boolean                                                           |          |
| `versions`                                  | array                                                             | yes      |
| `versions[].version`                        | integer 1–10000                                                   | yes      |
| `versions[].body`                           | string ≤ 200000                                                   |          |
| `versions[].pdf_file`                       | file path                                                         |          |
| `versions[].pdf_file_name`                  | string ≤ 255                                                      |          |
| `versions[].created_at`                     | date-time                                                         |          |
| `versions[].fields`                         | array                                                             |          |
| `versions[].fields[].key`                   | string (pattern)                                                  | yes      |
| `versions[].fields[].label`                 | string ≤ 120                                                      | yes      |
| `versions[].fields[].input_type`            | `text`, `resident`, `table`, `check`, `date`, `amount`            | yes      |
| `versions[].fields[].is_required`           | boolean                                                           |          |
| `versions[].fields[].picklist_key`          | key ref                                                           |          |
| `versions[].pdf_mappings`                   | array                                                             |          |
| `versions[].pdf_mappings[].pdf_field`       | string ≤ 255                                                      | yes      |
| `versions[].pdf_mappings[].pdf_field_type`  | `text`, `checkbox`, `radio`, `choice`, `push-button`, `signature` | yes      |
| `versions[].pdf_mappings[].pdf_on_state`    | string ≤ 64                                                       |          |
| `versions[].pdf_mappings[].token`           | string ≤ 80                                                       |          |
| `versions[].pdf_mappings[].checked_value`   | string ≤ 64                                                       |          |
| `versions[].pdf_mappings[].unchecked_value` | string ≤ 64                                                       |          |

- `kind` `rich-text` needs `body` in every version; `pdf` needs `pdf_file` (a fillable PDF up to the template PDF limit).
- Bodies use the unified placeholder syntax (`{{resident.full_name}}`, `{{field.purpose}}`) and are sanitized on import.
- Field keys match `^[a-z][a-z0-9_]{0,39}$`, unique per version; `table` fields need `picklist_key`.
- The highest version becomes the current version.

### 27. `form_transactions`

Default file `form_transactions.jsonl`, schema `form_transactions.schema.json`. Writes `form_transactions`, `official_receipts`. References: form_templates, residents.

| Field                 | Type                                                   | Required |
| --------------------- | ------------------------------------------------------ | -------- |
| `source_key`          | key                                                    | yes      |
| `source_number`       | string ≤ 64                                            |          |
| `import_note`         | string ≤ 255                                           |          |
| `template_key`        | key ref                                                | yes      |
| `template_version`    | integer ≥ 1                                            | yes      |
| `resident_key`        | key ref                                                | yes      |
| `status`              | `issued`, `released`, `cancelled`                      | yes      |
| `issued_at`           | date-time                                              | yes      |
| `released_at`         | date-time                                              |          |
| `cancelled_at`        | date-time                                              |          |
| `cancel_reason`       | string ≤ 255                                           |          |
| `verified_by_name`    | string ≤ 255                                           |          |
| `certified_by_name`   | string ≤ 255                                           |          |
| `or_number`           | string ≤ 32                                            |          |
| `amount`              | money                                                  |          |
| `fields`              | array                                                  |          |
| `fields[].key`        | string (pattern)                                       | yes      |
| `fields[].label`      | string ≤ 120                                           | yes      |
| `fields[].input_type` | `text`, `resident`, `table`, `check`, `date`, `amount` |          |
| `fields[].value`      | string or number or boolean                            | yes      |
| `fields[].display`    | string ≤ 5000                                          |          |

- `template_version` must exist on the template (`unknown_version`).
- `fields` are the filled-in values; resident placeholders are snapshotted from the imported resident at the issue date.

### 28. `sms_groups`

Default file `sms_groups.jsonl`, schema `sms_groups.schema.json`. Writes `sms_groups`, `sms_group_members`. References: residents.

| Field                     | Type         | Required |
| ------------------------- | ------------ | -------- |
| `source_key`              | key          | yes      |
| `name`                    | string ≤ 50  | yes      |
| `is_active`               | boolean      |          |
| `members`                 | array        |          |
| `members[].resident_key`  | key ref      |          |
| `members[].mobile_number` | mobile       |          |
| `members[].label`         | string ≤ 100 |          |

- Each member has `resident_key` or `mobile_number`, never both. Typed numbers are stored as E.164; duplicates are dropped (`duplicate_member`).

### 29. `sms_templates`

Default file `sms_templates.jsonl`, schema `sms_templates.schema.json`. Writes `sms_templates`.

| Field        | Type          | Required |
| ------------ | ------------- | -------- |
| `source_key` | key           | yes      |
| `title`      | string ≤ 50   | yes      |
| `body`       | string ≤ 1600 | yes      |

- Titles are unique per barangay; a clash gets ` (2)` appended.

## Validation and problem codes

The importer validates every entity file listed in the manifest before it writes anything:

1. The manifest: `format`, then `version`, then the manifest schema. Any failure stops with a message and exit code 1.
2. Each record against its entity schema (first error per record).
3. Duplicate `source_key` values per entity (and voucher item keys across vouchers).
4. Line counts against the manifest.

Any failure rejects the whole bundle: nothing is written, and the problem CSV lists every failing record. The console shows up to 20 failures as `entity source_key /json/pointer (keyword)`, never the values.

Then business rules run per record:

| Code                      | Severity                             | Meaning                                                                         |
| ------------------------- | ------------------------------------ | ------------------------------------------------------------------------------- |
| `schema_invalid`          | rejects bundle                       | Record does not match its schema.                                               |
| `invalid_json`            | rejects bundle                       | Line is not a JSON object. Key is `#<line>`.                                    |
| `duplicate_source_key`    | rejects bundle                       | Second record with the same key.                                                |
| `count_mismatch`          | rejects bundle                       | Manifest `count` differs from the file. Key is `*`.                             |
| `entity_file_missing`     | rejects bundle                       | File named in the manifest does not exist. Key is `*`.                          |
| `already_imported`        | skipped                              | Mapped by an earlier run. Counted, not listed in the CSV.                       |
| `dangling_reference`      | error or warning                     | See "Reference rules".                                                          |
| `missing_name`            | error                                | Resident with both names empty, or business without any owner.                  |
| `or_number_conflict`      | warning; error for other collections | See "OR numbers".                                                               |
| `form_number_conflict`    | warning                              | Cedula form number already used; cleared with a note.                           |
| `number_replaced`         | warning                              | Source number too long or taken; a new number was assigned.                     |
| `name_taken`              | warning                              | Unique name taken; ` (2)` appended.                                             |
| `username_renamed`        | warning                              | Username taken; `-<PSGC>` appended.                                             |
| `future_birthdate`        | warning                              | Birthdate after the import day dropped.                                         |
| `unknown_birthplace`      | warning                              | Birthplace PSGC code not loaded.                                                |
| `attachment_missing`      | warning; error for PDF templates     | File not found under `files/` or unsafe path.                                   |
| `attachment_unsupported`  | warning; error for PDF templates     | Wrong type, too large, or unreadable PDF form.                                  |
| `color_tag_skipped`       | skipped or warning                   | Color tagging is off for the barangay.                                          |
| `account_parent_invalid`  | error                                | Parent kind not allowed for the account kind.                                   |
| `account_kind_mismatch`   | error                                | Existing account with this code has another kind.                               |
| `budget_year_exists`      | skipped                              | The barangay already has a budget for that year.                                |
| `budget_line_not_posting` | warning                              | Line account has children or is a group; line dropped.                          |
| `duplicate_line`          | warning                              | Second line for the same account in a version; dropped.                         |
| `duplicate_version`       | error                                | Two budget or template versions with the same number.                           |
| `unknown_version`         | error                                | Issued form names a template version that does not exist.                       |
| `duplicate_field_key`     | error                                | Two template fields with the same key in one version.                           |
| `amount_below_minimum`    | error                                | Check below 1.00.                                                               |
| `check_conflict`          | error                                | Active check number or voucher already has a check.                             |
| `liquidation_conflict`    | error                                | Voucher already liquidated.                                                     |
| `duplicate_member`        | warning                              | SMS group member listed twice.                                                  |
| `record_failed`           | error                                | Database constraint failure on this record only; see the log for the SQL state. |

The problem CSV has three columns, `entity,source_key,problem`, and never holds names, numbers, or other values.

## Versioning policy

- The bundle `version` is an integer. Version 1 is frozen once a converter ships against it: no field is removed, renamed, or given a new meaning, and no optional field becomes required.
- Compatible additions to v1 (a new optional field, a new entity, a new enum value that older bundles never contain) may be made in place. Update the schema, this page, and the `BundleSchemaContractTest` expectations in the same change.
- Anything else is a new version. The importer keeps reading every version listed in `BundleFormat::SUPPORTED_VERSIONS`; a bundle with an unlisted version is rejected before any record is read.

## Adding version 2

1. Copy `resources/import-bundle/v1/` to `resources/import-bundle/v2/` and change every `$id` from `/v1/` to `/v2/`; set the manifest `version` const to 2.
2. Make the breaking changes in the v2 schemas and write `docs/import/bundle-format-v2.md` with a "Changes from v1" section.
3. Add 2 to `BundleFormat::SUPPORTED_VERSIONS`. `SchemaValidator` loads schemas for the bundle's version; pass `$bundle->version` where it is constructed.
4. Where v2 records differ, add a small upgrader that turns a v1 record into the v2 shape before the importers see it, so the importers only know the newest shape.
5. Add a test bundle for each supported version and run both through the same feature tests.
6. Publish the v2 folder to the converter and keep v1 until no converter writes it.
