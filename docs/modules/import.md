# Bundle import (operator runbook)

`php artisan bms:import:bundle` reads one import bundle and writes it into one onboarded barangay. It is one-way, per barangay, and safe to re-run. The bundle format and every field: [import/bundle-format-v1.md](../import/bundle-format-v1.md). Decisions: [ADR 0014](../adr/0014-versioned-import-bundle.md).

A bundle is produced outside this application by a converter. This system never connects to another database and never reads anything except the bundle directory.

## 1. Prerequisites

| Item                   | Check                                                                                                                                                                                                |
| ---------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Target barangay exists | Onboarded by central staff (Central → Barangays). Note its PSGC code. The standard chart of accounts is installed at onboarding.                                                                     |
| Reference data         | `php artisan db:seed --class=LookupSeeder` has run (shared lookups are matched by label), `php artisan bms:roles:sync` has run, and the PSGC dataset is loaded if residents carry `birthplace_psgc`. |
| Flags decided          | Turn on `political-color-tagging` for the barangay **before** the import only if color tags must be kept. Every other flag can change later.                                                         |
| Bundle                 | A directory with `manifest.json`, the `.jsonl` files, and `files/`, copied to the server that runs the command. Treat it as personal data: encrypted at rest, deleted after the import is accepted.  |
| Disk                   | Free space on the private disk for the attachments in `files/`.                                                                                                                                      |
| `gd`, `fileinfo`       | PHP extensions on the server that runs the command.                                                                                                                                                  |

## 2. Dry run first

```sh
php artisan bms:import:bundle /srv/imports/barangay-uno --barangay=9902801001 --dry-run
```

The dry run validates the whole bundle, runs every business rule and database constraint inside one transaction, and rolls it back. It stores no attachments and writes nothing except the problem report (§4).

| Option            | Meaning                                                                                                             |
| ----------------- | ------------------------------------------------------------------------------------------------------------------- |
| `path`            | Required. The bundle directory.                                                                                     |
| `--barangay=PSGC` | The onboarded target barangay. Optional when the manifest has `barangay_psgc`; the flag wins when both are present. |
| `--dry-run`       | Validate and report; write nothing.                                                                                 |
| `--only=a,b`      | Import only these entities (names in §6). They still run in dependency order.                                       |
| `--operator=user` | Username of the staff member running the import; stored on the run and the audit rows.                              |

## 3. Validation

Before anything is written, every entity file named in the manifest is checked against the JSON Schemas in `resources/import-bundle/v1/`, then for duplicate keys and line counts. A single failure rejects the bundle:

- A bad manifest (missing, not JSON, wrong `format`, unsupported `version`, unknown entity) stops with one message, for example `Bundle format version 2 is not supported; this importer reads version 1.`
- Bad records are listed as `entity source_key /field (keyword)` (first 20 on screen, all in the problem CSV). Values are never printed.

Send the report back to whoever produced the bundle; fix the converter, not the bundle by hand.

## 4. Read the report

The command prints, in this order:

1. The bundle version, generator, and generation time.
2. One line per entity with its record count as it starts.
3. The entity table: **Read**, **Imported**, **Skipped**, **Warnings**, **Errors**. For every entity, Read = Imported + Skipped + Errors. Warnings are notes on records that were imported.
4. Problem codes with counts (all codes: [bundle-format-v1.md](../import/bundle-format-v1.md#validation-and-problem-codes)).
5. The problem CSV path, how many document sequences were continued, and the run number.

| Column   | Meaning                                                                                                           |
| -------- | ----------------------------------------------------------------------------------------------------------------- |
| Imported | New row written, or mapped to an existing equal row (shared lookup, same-name purok, existing chart account).     |
| Skipped  | Not imported on purpose: already imported by an earlier run, color tags while the flag is off, budget year taken. |
| Errors   | Could not be imported: missing reference, conflict, constraint failure. Fix the bundle and run again.             |

The problem CSV is on the private disk at `storage/app/private/barangays/<id>/imports/<time>-<token>-problems.csv` (`dry-run-` in the token for dry runs) with the columns `entity,source_key,problem`. It never holds names, numbers, or other values.

The run is stored in `import_runs` (status, options with the bundle's generator and version, versioned report JSON `{"v": 1, ...}`, CSV path, operator, start and finish). The audit log gets "Data import started" and "Data import finished" rows with counts only. Rejected bundles and dry runs create no run and no audit rows.

| Exit code | When                                                                                         |
| --------- | -------------------------------------------------------------------------------------------- |
| 0         | Imported (or dry-ran) with no record errors.                                                 |
| 1         | The bundle was rejected, or at least one record errored.                                     |
| 2         | Invalid invocation: unknown barangay, entity, or operator, or `--only` missing a dependency. |

## 5. Run and re-run

```sh
php artisan bms:import:bundle /srv/imports/barangay-uno --barangay=9902801001 --operator=central
```

- Records are read in file order, 500 at a time (100 for residents and blotters, 50 for form templates), and each chunk is written in one transaction. If a chunk fails, its records are retried one by one; only the failing ones are reported as `record_failed`.
- Every imported record is recorded in `import_id_map` (barangay, entity, source key, target table, target id; unique on the first three). A re-run skips mapped records, so it never duplicates and adds nothing when nothing changed. An interrupted run resumes where it stopped.
- To fix a problem, regenerate the bundle with the same `source_key` values and run again; only records that were not imported are retried. Records that were imported are never updated.
- `--only` refuses an entity whose dependency is in the bundle but was neither selected nor imported by an earlier completed run (exit code 2, with the entity to add).

## 6. Entities

In import order. Fields and rules per entity: [bundle-format-v1.md](../import/bundle-format-v1.md#entities).

| Entity                   | Target                                                                    |
| ------------------------ | ------------------------------------------------------------------------- |
| `lookups`                | `lookup_values` (optional list; other records reference lookups by label) |
| `puroks`                 | `puroks`                                                                  |
| `users`                  | `users`, `user_roles`                                                     |
| `color_categories`       | `color_categories` (only with `political-color-tagging` on)               |
| `residents`              | `residents`, `resident_sector`, `resident_political_tags`, photos         |
| `family_members`         | `family_members`                                                          |
| `educations`             | `resident_educations`                                                     |
| `work_histories`         | `resident_work_histories`                                                 |
| `id_cards`               | `id_cards`, `official_receipts`                                           |
| `barangay_clearances`    | `barangay_clearances`, `official_receipts`                                |
| `indigency_certificates` | `indigency_certificates`                                                  |
| `businesses`             | `businesses`                                                              |
| `business_clearances`    | `business_clearances`, `official_receipts`                                |
| `cedulas`                | `cedulas`                                                                 |
| `blotters`               | `blotters`, `blotter_parties`, `blotter_photos`                           |
| `collection_types`       | `collection_types`                                                        |
| `other_collections`      | `other_collections`, `official_receipts`                                  |
| `accounts`               | `accounts` (matched by code; groups by type)                              |
| `budgets`                | `budgets`, `budget_versions`, `budget_lines`                              |
| `bank_accounts`          | `bank_accounts`                                                           |
| `vendors`                | `vendors`                                                                 |
| `vouchers`               | `vouchers`, `voucher_items`                                               |
| `checks`                 | `checks`                                                                  |
| `liquidations`           | `liquidations`, `liquidation_items`, `official_receipts`                  |
| `form_picklists`         | `form_picklists`, `form_picklist_items`                                   |
| `form_templates`         | `form_templates`, versions, fields, PDF mappings, template PDFs           |
| `form_transactions`      | `form_transactions`, `official_receipts`                                  |
| `sms_groups`             | `sms_groups`, `sms_group_members`                                         |
| `sms_templates`          | `sms_templates`                                                           |

## 7. What the importer enforces

The importer applies this system's rules, not the source system's:

| Rule             | Behavior                                                                                                                                                  |
| ---------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Users            | Created inactive with a random unusable password and the bundle's barangay role. A password field rejects the bundle. Username clashes get `-<PSGC>`.     |
| Color tags       | Imported only when `political-color-tagging` is on at import time (ADR 0006); otherwise skipped and counted.                                              |
| OR numbers       | Registered in `official_receipts`; first active use wins; later documents keep their row with the OR cleared and a note (other collections are rejected). |
| Document numbers | Source numbers are kept when unique; otherwise a new number is assigned and the source number is kept in `source_number`.                                 |
| Sequences        | Raised to the highest imported counter per kind and year after the run, so new numbers continue without collision.                                        |
| Attachments      | Resident and blotter photos must be JPEG, PNG, or WebP within the upload limits; template PDFs must be readable PDF forms. Stored on the private disk.    |
| Resident fields  | No voter ID without the voter flag, no death data unless deceased, no alternate address for permanent residents, no future birthdates.                    |
| Rich text        | Template bodies are sanitized like any saved template.                                                                                                    |

## 8. After the import

1. **Activate users.** Administration → Accounts: review roles, reset each password, then activate. Imported users cannot sign in before that.
2. **Review warnings.** Open the CSV; check `or_number_conflict`, `number_replaced`, `form_number_conflict` (see `import_note` on the record), `dangling_reference`, and every error.
3. **Hide or merge** barangay-specific lookup values created from unexpected labels (Administration → Lookup values).
4. **Turn on flags** the barangay uses (`resident-photo`, `sms`, finance options, and so on); see `docs/platform/feature-flags.md`.
5. **Sample-check** 20 residents and 20 documents against the source printouts, then delete the bundle copy from the server.
